<?php

namespace App\Application\Catalog\Sections\Handler;

use App\Application\Catalog\Sections\Mapper\CatalogSectionFrontendMapper;
use App\Infrastructure\Catalog\Persistence\CatalogVersionRepository;
use App\Infrastructure\Catalog\Persistence\CategoryRepository;
use App\Shared\Exception\BadRequestException;
use App\Shared\Exception\NotFoundException;
use App\Shared\Time\ClockInterface;

final class GetCatalogSectionsHandler
{
    public function __construct(
        private CatalogVersionRepository $versionRepository,
        private CategoryRepository $categoryRepository,
        private CatalogSectionFrontendMapper $sectionMapper,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $level): array
    {
        $maxLevel = $this->readLevel($level);

        $activeTable = $this->versionRepository->getActiveCategoryTableName();
        if ($activeTable === null) {
            throw new NotFoundException('Active catalog category table not found');
        }

        $rows = $this->categoryRepository->findActiveTreeRowsForFrontend($activeTable, $maxLevel);
        $childrenByParent = $this->buildChildrenByParent($rows);
        $responseLevel = $this->calculateResponseLevel($rows);

        return [
            'success' => true,
            'timestamp' => $this->clock->nowFormatted(),
            'data' => [
                'level' => $responseLevel,
                'count' => count($rows),
                'items' => array_map(
                    fn (array $row): array => $this->sectionMapper->mapItem($row),
                    $rows
                ),
                'tree' => $this->buildTree($childrenByParent, null),
            ],
        ];
    }

    private function readLevel(mixed $level): ?int
    {
        if ($level === null) {
            return null;
        }

        if (is_bool($level) || is_float($level) || !is_scalar($level)) {
            throw new BadRequestException('level must be an integer between 1 and 100');
        }

        $level = (string) $level;

        if (!preg_match('/^[1-9][0-9]{0,2}$/', $level)) {
            throw new BadRequestException('level must be an integer between 1 and 100');
        }

        $levelValue = (int) $level;

        if ($levelValue < 1 || $levelValue > 100) {
            throw new BadRequestException('level must be between 1 and 100');
        }

        return $levelValue;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function calculateResponseLevel(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return max(array_map(
            static fn (array $row): int => (int) $row['level'],
            $rows
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildChildrenByParent(array $rows): array
    {
        $childrenByParent = [];

        foreach ($rows as $row) {
            $parentExternalId = $row['parent_external_id'] !== null
                ? (string) $row['parent_external_id']
                : null;

            $childrenByParent[$this->parentKey($parentExternalId)][] = $row;
        }

        return $childrenByParent;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $childrenByParent
     *
     * @return array<string, mixed>|\stdClass
     */
    private function buildTree(array $childrenByParent, ?string $parentExternalId): array|\stdClass
    {
        $children = $childrenByParent[$this->parentKey($parentExternalId)] ?? [];

        if ($children === []) {
            return new \stdClass();
        }

        $tree = [];

        foreach ($children as $child) {
            $externalId = (string) $child['external_id'];
            $tree[$externalId] = $this->buildTree($childrenByParent, $externalId);
        }

        return $tree;
    }

    private function parentKey(?string $parentExternalId): string
    {
        return $parentExternalId ?? '__root__';
    }

}
