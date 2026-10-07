<?php

namespace App\Controller\Api\UploadFile;

use App\Application\Import\UploadFile\Command\UploadFileCommand;
use App\Application\Import\UploadFile\Handler\UploadFileHandler;
use App\Application\ImportProducts\Command\ImportProductsCommand;
use App\Application\ImportProducts\Handler\ImportProductsHandler;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UploadProductsController extends AbstractController
{
    public function __construct(
        private UploadFileHandler $uploadHandler,
        private ImportProductsHandler $importHandler,
        private int $matchThreshold,
    ) {}

    #[Route('/import/products', name: 'catalog_import_products', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $supplierId = null;

        $stockId = $request->request->get('stock_id');

        $currency = $request->request->get('currency', 'RUB');

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse([
                'success' => false,
                'error' => 'file is required',
            ], 400);
        }

        $importSessionId = Uuid::uuid4()->toString();

        $uploadCommand = new UploadFileCommand($file);
        $uploadResponse = $this->uploadHandler->handle($uploadCommand);

        $importCommand = new ImportProductsCommand(
            importSessionId: $importSessionId,
            fullPath: $uploadResponse->fullPath,
            originalName: $uploadResponse->originalName,
            supplierId: $supplierId,
            stockId: $stockId,
            currency: $currency,
            threshold: $this->matchThreshold,
        );

        $importResult = $this->importHandler->handle($importCommand);

        return new JsonResponse(
            $importResult->toArray(),
            JsonResponse::HTTP_OK,
        );
    }
}
