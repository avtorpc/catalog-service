<?php

namespace App\Application\ImportCatalog\Command;

final class ImportCatalogCommand
{
    public function __construct(
        public string $fullPath,
        public string $originalName,
    ) {}
}
