<?php

namespace App\Application\Import\UploadFile\DTO;

final class StoredImportFile
{
    public function __construct(
        public string $originalName,
        public string $storedName,
        public string $path,
        public string $fullPath,
        public int $size,
        public string $mimeType,
    ) {}
}
