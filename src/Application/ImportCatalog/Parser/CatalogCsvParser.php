<?php

namespace App\Application\ImportCatalog\Parser;

use App\Application\ImportCatalog\DTO\CatalogRow;
use App\Infrastructure\Catalog\Slug\SlugGenerator;

final class CatalogCsvParser
{
    public function __construct(
        private SlugGenerator $slugGenerator,
    ) {
    }

    /**
     * @return \Generator<CatalogRow>
     */
    public function parse(string $filePath): \Generator
    {
        $file = new \SplFileObject($filePath);
        $file->setFlags(\SplFileObject::READ_CSV);
        $file->setCsvControl(';');

        $lineNumber = 0;

        foreach ($file as $row) {
            $lineNumber++;

            if (!$row || count($row) < 3) {
                continue;
            }

            /**
             * Header skip
             */
            if ($lineNumber === 1 && strtoupper(trim((string) $row[0])) === 'ID') {
                continue;
            }

            [$id, $parentExternalId, $name] = $row;

            $name = trim($name);
            $externalId = $this->normalizeExternalId($id);
            $slug = $this->buildStableSlug($name, $externalId);

            yield new CatalogRow(
                uuid: $this->slugGenerator->generateUuid($slug),
                externalId: $externalId,
                parentExternalId: $this->normalizeParentExternalId($parentExternalId),
                name: $name,
                slug: $slug,
                lineNumber: $lineNumber,
            );
        }
    }

    private function buildStableSlug(string $name, string $externalId): string
    {
        $baseSlug = $this->slugGenerator->generate($name);
        $externalSlug = $this->slugGenerator->generate($externalId);

        return $baseSlug . '-' . $externalSlug;
    }

    private function normalizeExternalId(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeParentExternalId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        return $value;
    }
}
