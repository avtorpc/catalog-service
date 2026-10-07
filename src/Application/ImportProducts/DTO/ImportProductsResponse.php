<?php

namespace App\Application\ImportProducts\DTO;

final class ImportProductsResponse
{
    public function __construct(
        public readonly string $importSessionId,
        public readonly string $status,
        public readonly int $total,
        public readonly int $matched,
        public readonly int $unmatched,
        public readonly array $products,
    ) {}

    public function toArray(): array
    {
        return [
            'import_session_id' => $this->importSessionId,
            'success' => true,
            'status' => $this->status,
            'total' => $this->total,
            'matched' => $this->matched,
            'unmatched' => $this->unmatched,
            'products' => $this->products,
        ];
    }
}
