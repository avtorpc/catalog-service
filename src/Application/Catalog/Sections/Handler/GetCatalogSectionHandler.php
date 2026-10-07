<?php

namespace App\Application\Catalog\Sections\Handler;

use App\Application\Catalog\Sections\Mapper\CatalogSectionFrontendMapper;
use App\Infrastructure\Catalog\Persistence\CatalogVersionRepository;
use App\Infrastructure\Catalog\Persistence\CategoryRepository;
use App\Shared\Exception\BadRequestException;
use App\Shared\Exception\NotFoundException;
use App\Shared\Time\ClockInterface;

final class GetCatalogSectionHandler
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
    public function handle(string $slug): array
    {
        $this->validateSlug($slug);

        $activeTable = $this->versionRepository->getActiveCategoryTableName();
        if ($activeTable === null) {
            throw new NotFoundException('Active catalog category table not found');
        }

        $rows = $this->categoryRepository->findActiveSectionBranchRowsForFrontend($activeTable, $slug);
        if ($rows === []) {
            throw new NotFoundException("Catalog section {$slug} not found");
        }

        $target = null;
        $parents = [];
        $children = [];

        foreach ($rows as $row) {
            $relationType = (string) $row['relation_type'];
            $mapped = $this->sectionMapper->mapItem($row);

            if ($relationType === 'target') {
                $target = $mapped;
                continue;
            }

            if ($relationType === 'parent') {
                $parents[] = $mapped;
                continue;
            }

            $children[] = $mapped;
        }

        if ($target === null) {
            throw new NotFoundException("Catalog section {$slug} not found");
        }

        return [
            'success' => true,
            'timestamp' => $this->clock->nowFormatted(),
            'data' => [
                'slug' => $slug,
                'item' => $target,
                'parents' => $parents,
                'childrenCount' => count($children),
                'children' => $children,
                'tree' => [
                    $target['id'] => $this->buildTree($this->buildChildrenByParent($rows), (string) $target['id']),
                ],
            ],
        ];
    }

    private function validateSlug(string $slug): void
    {
        if ($slug === '' || strlen($slug) > 255 || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
            throw new BadRequestException('slug must contain only lowercase latin letters, numbers, underscores and hyphens');
        }
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
            if ((string) $row['relation_type'] === 'parent') {
                continue;
            }

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
    private function buildTree(array $childrenByParent, string $parentExternalId): array|\stdClass
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
