<?php

namespace App\Application\Import\UploadFile\Service;

use App\Application\Import\UploadFile\DTO\StoredImportFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Ramsey\Uuid\Uuid;

final class LocalImportFileStorage
{
    public function __construct(
        #[Autowire('%catalog.import.upload_dir%')]
        private string $uploadDir
    ) {}

    public function save(UploadedFile $file): StoredImportFile
    {
        // 1. Ensure directory exists
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) {
                throw new \RuntimeException(sprintf(
                    'Upload directory "%s" could not be created',
                    $this->uploadDir
                ));
            }
        }

        // 2. Safe extension
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');

        // 3. UUID filename
        $storedName = Uuid::uuid4()->toString() . '.' . $extension;

        // 4. Move file FIRST (important)
        $file->move($this->uploadDir, $storedName);

        $path = $this->uploadDir;

        $fullPath = rtrim($this->uploadDir, '/') . '/' . $storedName;

        // 5. Validate file exists AFTER move
        if (!file_exists($path)) {
            throw new \RuntimeException('File was not saved correctly');
        }

        // 6. SAFE MIME detection (NO Symfony guesser)
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        return new StoredImportFile(
            originalName: $file->getClientOriginalName(),
            storedName: $storedName,
            path: $path,
            fullPath: $fullPath,
            size: filesize($path),
            mimeType: $mimeType,
        );
    }
}
