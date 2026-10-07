<?php

namespace App\Controller\Api\UploadFile;

use App\Application\Import\UploadFile\Handler\UploadFileHandler;
use App\Application\Import\UploadFile\Mapper\UploadFileMapper;
use App\Application\ImportCatalog\Command\ImportCatalogCommand;
use App\Application\ImportCatalog\Handler\ImportCatalogHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UploadFileController extends AbstractController
{
    public function __construct(
        private UploadFileHandler $uploadHandler,
        private ImportCatalogHandler $importHandler,
    ) {}

    #[Route('/import/file', name: 'catalog_import_file', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {

        /**
         * 1. Validate + map request → Command
         */
        $command = UploadFileMapper::fromRequest($request);

        /**
         * 2. Business logic (storage + future parsing pipeline)
         */
        $response = $this->uploadHandler->handle($command);

        /**
         * 2. IMPORT STEP (строго через fullPath)
         */
        $importCommand = new ImportCatalogCommand(
            fullPath: $response->fullPath,
            originalName: $response->originalName
        );

        /// парсинг
        $importResult = $this->importHandler->handle($importCommand);

        /**
         * 3. RESPONSE (объединяем результаты)
         */
        return new JsonResponse([
            'success' => true,
            'upload' => $response->toArray(),
            'import' => $importResult->toArray(),
        ]);
    }
}
