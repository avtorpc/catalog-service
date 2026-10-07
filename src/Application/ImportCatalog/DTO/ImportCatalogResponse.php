<?php

namespace App\Application\ImportCatalog\DTO;

final class ImportCatalogResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $fileName,
        public readonly int $rowsProcessed,
        public readonly int $maxDepth,
        public readonly \DateTimeImmutable $startedAt,
        public readonly \DateTimeImmutable $finishedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'file_name' => $this->fileName,
            'rows_processed' => $this->rowsProcessed,
            'max_depth' => $this->maxDepth,
            'started_at' => $this->startedAt->format(DATE_ATOM),
            'finished_at' => $this->finishedAt->format(DATE_ATOM),
            'duration_ms' => $this->finishedAt->getTimestamp() - $this->startedAt->getTimestamp(),
        ];
    }
}
