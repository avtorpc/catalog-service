<?php

namespace App\Infrastructure\Catalog\Persistence;

use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ProductFeedRepository
{
    public function __construct(
        private Connection $connection,
        private SchemaSqlHelper $schemaSqlHelper,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveCategories(string $categoryTableName): array
    {
        $categoryTable = $this->schemaSqlHelper->table($categoryTableName);

        return $this->connection->fetchAllAssociative(
            "
            SELECT
                external_id,
                parent_external_id,
                name,
                slug
            FROM {$categoryTable}
            WHERE is_active = true
            ORDER BY level ASC, sort_order ASC, name ASC, external_id ASC
            "
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveProducts(?string $supplierId, ?string $stockId): array
    {
        $productTable = $this->schemaSqlHelper->table('product_cards');
        $where = [
            "status = 'active'",
            'currency IS NOT NULL',
            "currency <> ''",
            'price IS NOT NULL',
            'price > 0',
        ];
        $params = [];
        $types = [];

        if ($supplierId !== null) {
            $where[] = 'supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
            $types['supplier_id'] = ParameterType::STRING;
        }

        if ($stockId !== null) {
            $where[] = 'stock_id = :stock_id';
            $params['stock_id'] = $stockId;
            $types['stock_id'] = ParameterType::STRING;
        }

        return $this->connection->fetchAllAssociative(
            "
            SELECT
                id,
                category_id,
                external_code,
                supplier_id,
                stock_id,
                name,
                slug,
                quantity,
                unit,
                price,
                currency,
                image_url
            FROM {$productTable}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY name ASC, external_code ASC, id ASC
            ",
            $params,
            $types
        );
    }

    /**
     * @return array<int, string>
     */
    public function findActiveCurrencies(?string $supplierId, ?string $stockId): array
    {
        $productTable = $this->schemaSqlHelper->table('product_cards');
        $where = [
            "status = 'active'",
            'currency IS NOT NULL',
            "currency <> ''",
            'price IS NOT NULL',
            'price > 0',
        ];
        $params = [];
        $types = [];

        if ($supplierId !== null) {
            $where[] = 'supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
            $types['supplier_id'] = ParameterType::STRING;
        }

        if ($stockId !== null) {
            $where[] = 'stock_id = :stock_id';
            $params['stock_id'] = $stockId;
            $types['stock_id'] = ParameterType::STRING;
        }

        $rows = $this->connection->fetchFirstColumn(
            "
            SELECT DISTINCT currency
            FROM {$productTable}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY currency ASC
            ",
            $params,
            $types
        );

        return array_values(array_map('strval', $rows));
    }
}
