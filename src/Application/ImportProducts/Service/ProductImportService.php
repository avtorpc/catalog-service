<?php

namespace App\Application\ImportProducts\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use App\Infrastructure\Catalog\Persistence\CatalogVersionRepository;
use App\Infrastructure\Catalog\Persistence\CategoryRepository;
use App\Infrastructure\DB\SchemaSqlHelper;

final class ProductImportService
{
    public function __construct(
        private Connection $connection,
        private SchemaSqlHelper $schemaSqlHelper,
        private CatalogVersionRepository $versionRepository,
        private CategoryRepository $categoryRepository,
    ) {}

    /**
     * @return array<string, array{fullPath: string}> keyed by external_id
     */
    public function loadActiveCategoryPaths(): array
    {
        $tableName = $this->versionRepository->getActiveCategoryTableName();

        if ($tableName === null) {
            return [];
        }

        return $this->categoryRepository->findFullPathsByTable($tableName);
    }

    public function saveResult(
        string $startId,
        int $lineNumber,
        string $nomenclature,
        ?string $externalCode,
        ?float $quantity,
        ?string $unit,
        ?float $price,
        ?string $categoryId,
        ?float $matchPercent,
        ?string $fullPath,
        string $status,
    ): void {
        $table = $this->schemaSqlHelper->table('product_import_results');

        $this->connection->insert($table, [
            'import_session_id' => $startId,
            'line_number' => $lineNumber,
            'nomenclature' => $nomenclature,
            'external_code' => $externalCode,
            'quantity' => $quantity,
            'unit' => $unit,
            'price' => $price,
            'category_id' => $categoryId,
            'match_percent' => $matchPercent,
            'full_path' => $fullPath,
            'status' => $status,
        ], [
            'quantity' => Types::DECIMAL,
            'price' => Types::DECIMAL,
            'match_percent' => Types::DECIMAL,
        ]);
    }

    public function saveProductCard(
        string $importSessionId,
        ?string $categoryId,
        ?string $supplierId,
        ?string $stockId,
        string $name,
        string $slug,
        ?string $externalCode,
        ?float $quantity,
        ?string $unit,
        ?float $price,
        string $currency,
        ?float $weight = null,
        ?float $width = null,
        ?float $height = null,
        ?float $length = null,
    ): void {
        $table = $this->schemaSqlHelper->table('product_cards');

        $status = ($categoryId !== null && $quantity !== null && $quantity > 0) ? 'active' : 'inactive';

        $this->connection->executeStatement(
            "INSERT INTO {$table} (category_id, external_code, supplier_id, stock_id, name, slug, quantity, unit, price, currency, weight, width, height, length, image_url, import_session_id, status, created_at, updated_at)
             VALUES (:categoryId, :externalCode, :supplierId, :stockId, :name, :slug, :quantity, :unit, :price, :currency, :weight, :width, :height, :length, NULL, :importSessionId, :status, NOW(), NOW())
             ON CONFLICT (slug)
             DO UPDATE SET category_id = EXCLUDED.category_id, name = EXCLUDED.name, external_code = EXCLUDED.external_code, quantity = EXCLUDED.quantity, unit = EXCLUDED.unit, price = EXCLUDED.price, currency = EXCLUDED.currency, weight = EXCLUDED.weight, width = EXCLUDED.width, height = EXCLUDED.height, length = EXCLUDED.length, import_session_id = EXCLUDED.import_session_id, status = EXCLUDED.status, updated_at = NOW()",
            [
                'categoryId' => $categoryId,
                'externalCode' => $externalCode,
                'supplierId' => $supplierId,
                'stockId' => $stockId,
                'name' => $name,
                'slug' => $slug,
                'quantity' => $quantity,
                'unit' => $unit,
                'price' => $price,
                'currency' => $currency,
                'weight' => $weight,
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'importSessionId' => $importSessionId,
                'status' => $status,
            ],
            [
                'quantity' => Types::DECIMAL,
                'price' => Types::DECIMAL,
                'weight' => Types::DECIMAL,
                'width' => Types::DECIMAL,
                'height' => Types::DECIMAL,
                'length' => Types::DECIMAL,
            ]
        );
    }
}
