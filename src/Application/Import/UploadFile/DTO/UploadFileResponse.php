<?php

namespace App\Application\Import\UploadFile\DTO;

final class UploadFileResponse
{
    public function __construct(
        public bool $success,
        public string $originalName,
        public string $storedName,
        public int $size,
        public string $path,
        public string $fullPath
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'original_name' => $this->originalName,
            'stored_name' => $this->storedName,
            'size' => $this->size,
            'path' => $this->path,
            'full_path' => $this->fullPath,
        ];
    }
}
