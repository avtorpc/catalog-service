<?php

namespace App\Application\ImportCatalog\DTO;

final class CatalogRow
{
    public function __construct(
        public string $uuid,
        public string $externalId,
        public ?string $parentExternalId,
        public string $name,
        public string $slug,
        public int $lineNumber,
    ) {
    }
}
