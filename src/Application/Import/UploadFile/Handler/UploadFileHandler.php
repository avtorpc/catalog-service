<?php

namespace App\Application\Import\UploadFile\Handler;

use App\Application\Import\UploadFile\Command\UploadFileCommand;
use App\Application\Import\UploadFile\DTO\UploadFileResponse;
use App\Application\Import\UploadFile\Service\LocalImportFileStorage;
use Psr\Log\LoggerInterface;

final class UploadFileHandler
{
    public function __construct(
        private LocalImportFileStorage $storage,
        private LoggerInterface $logger
    ) {}

    public function handle(UploadFileCommand $command): UploadFileResponse
    {
        $stored = $this->storage->save($command->file);

        $this->logger->info('Catalog import uploaded', [
            'original_name' => $stored->originalName,
            'stored_name' => $stored->storedName,
            'path' => $stored->path,
            'size' => $stored->size,
        ]);

        return new UploadFileResponse(
            success: true,
            originalName: $stored->originalName,
            storedName: $stored->storedName,
            size: $stored->size,
            path: $stored->path,
            fullPath: $stored->fullPath
        );
    }
}
