<?php

namespace App\Application\ImportProducts\Handler;

use App\Application\ImportProducts\Command\ImportProductsCommand;
use App\Application\ImportProducts\DTO\ImportProductsResponse;
use App\Application\ImportProducts\Parser\ProductCsvParser;
use App\Application\ImportProducts\Service\ProductImportService;
use App\Application\ImportProducts\Matcher\ProductCategoryMatcher;
use App\Infrastructure\Catalog\Slug\SlugGenerator;

final class ImportProductsHandler
{
    public function __construct(
        private ProductCsvParser $parser,
        private ProductCategoryMatcher $matcher,
        private ProductImportService $importService,
        private SlugGenerator $slugGenerator,
    ) {}

    public function handle(ImportProductsCommand $command): ImportProductsResponse
    {
        $categoryPaths = $this->importService->loadActiveCategoryPaths();

        $this->matcher->loadCategoryPaths($categoryPaths);

        $rows = $this->parser->parse($command->fullPath);

        $total = 0;
        $matched = 0;
        $unmatched = 0;
        $products = [];

        foreach ($rows as $row) {
            $total++;

            $result = $this->matcher->findBestMatch($row->nomenclature);

            $categoryId = null;
            $categoryPath = null;
            $matchPercent = null;
            $fullPath = null;
            $statusLabel = 'unmatched';

            if ($result['categoryId'] !== null && $result['percent'] >= $command->threshold) {
                $categoryId = $result['categoryId'];
                $categoryPath = $result['fullPath'];
                $matchPercent = $result['percent'];
                $fullPath = $result['fullPath'];
                $statusLabel = 'matched';
                $matched++;
            } else {
                $unmatched++;
            }

            $this->importService->saveResult(
                startId: $command->importSessionId,
                lineNumber: $row->lineNumber,
                nomenclature: $row->nomenclature,
                externalCode: $row->externalCode,
                quantity: $row->quantity,
                unit: $row->unit,
                price: $row->price,
                categoryId: $categoryId,
                matchPercent: $matchPercent,
                fullPath: $fullPath,
                status: $statusLabel,
            );

            $slug = $this->slugGenerator->generate($row->nomenclature);
            if ($row->externalCode !== null) {
                $slug .= '_' . $row->externalCode;
            }
            if (!empty($command->stockId)) {
                $slug .= '_' . $command->stockId;
            }

            $this->importService->saveProductCard(
                importSessionId: $command->importSessionId,
                categoryId: $categoryId,
                supplierId: $command->supplierId,
                stockId: $command->stockId,
                name: $row->nomenclature,
                slug: $slug,
                externalCode: $row->externalCode,
                quantity: $row->quantity,
                unit: $row->unit,
                price: $row->price,
                currency: $command->currency,
            );

            $products[] = [
                'name' => $row->nomenclature,
                'sku' => $row->externalCode,
                'category_id' => $categoryId,
                'category_path' => $categoryPath,
                'quantity' => $row->quantity,
                'unit' => $row->unit,
                'price' => $row->price,
                'currency' => $command->currency,
                'status' => $statusLabel === 'matched' ? 'active' : 'inactive',
            ];
        }

        return new ImportProductsResponse(
            importSessionId: $command->importSessionId,
            status: 'completed',
            total: $total,
            matched: $matched,
            unmatched: $unmatched,
            products: $products,
        );
    }
}
