<?php

namespace App\Application\ImportProducts\DTO;

final class ProductRow
{
    public function __construct(
        public string $nomenclature,
        public ?string $externalCode,
        public ?float $quantity,
        public ?string $unit,
        public ?float $price,
        public int $lineNumber,
    ) {}
}
