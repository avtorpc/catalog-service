<?php

namespace App\Application\ImportProducts\Command;

final class ImportProductsCommand
{
    public function __construct(
        public string $importSessionId,
        public string $fullPath,
        public string $originalName,
        public ?string $supplierId,
        public ?string $stockId,
        public string $currency,
        public int $threshold,
    ) {}
}
