<?php

namespace App\Application\Catalog\Product\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use App\Infrastructure\Catalog\Persistence\CatalogVersionRepository;
use App\Infrastructure\Catalog\Persistence\CategoryRepository;
use App\Infrastructure\DB\SchemaSqlHelper;

final class ProductService
{
    public function __construct(
        private Connection $connection,
        private SchemaSqlHelper $schemaSqlHelper,
        private CatalogVersionRepository $versionRepository,
        private CategoryRepository $categoryRepository,
    ) {}

    public function findProducts(?string $stockId, ?string $categoryId, int $page, int $limit): array
    {
        $table = $this->schemaSqlHelper->table('product_cards');
        $offset = ($page - 1) * $limit;
        $params = [];
        $types = [];
        $where = [];

        if ($stockId !== null) {
            $where[] = 'stock_id = :stock_id';
            $params['stock_id'] = $stockId;
            $types['stock_id'] = Types::STRING;
        }

        if ($categoryId !== null) {
            $activeTable = $this->versionRepository->getActiveCategoryTableName();
            if ($activeTable !== null) {
                $ids = $this->categoryRepository->findDescendantExternalIds($activeTable, $categoryId);
                if (!empty($ids)) {
                    $placeholders = [];
                    foreach ($ids as $i => $id) {
                        $key = "category_id_{$i}";
                        $placeholders[] = ":$key";
                        $params[$key] = $id;
                        $types[$key] = Types::STRING;
                    }
                    $where[] = 'category_id IN (' . implode(',', $placeholders) . ')';
                } else {
                    $where[] = '1 = 0';
                }
            } else {
                $where[] = '1 = 0';
            }
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$table} {$whereClause}",
            $params,
            $types,
        );

        $items = $this->connection->fetchAllAssociative(
            "SELECT * FROM {$table} {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $params,
            $types,
        );

        $items = $this->enrichWithCategory($items);

        return [
            'items' => $items,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        $table = $this->schemaSqlHelper->table('product_cards');

        $product = $this->connection->fetchAssociative(
            "SELECT * FROM {$table} WHERE slug = :slug",
            ['slug' => $slug],
            ['slug' => Types::STRING],
        );

        if (!$product) {
            return null;
        }

        [$product] = $this->enrichWithCategory([$product]);

        return $product;
    }

    /**
     * Добавляет к карточкам товара название/полный путь узла дерева категорий,
     * к которому привязана карточка (либо null, если категория не привязана).
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithCategory(array $items): array
    {
        if (count($items) === 0) {
            return $items;
        }

        $activeTable = $this->versionRepository->getActiveCategoryTableName();
        $paths = $activeTable !== null
            ? $this->categoryRepository->findFullPathsByTable($activeTable)
            : [];

        foreach ($items as &$item) {
            $categoryId = $item['category_id'] ?? null;
            $path = $categoryId !== null ? ($paths[(string) $categoryId]['fullPath'] ?? null) : null;

            $item['category_path'] = $path;
            $item['category_name'] = $path !== null ? $this->lastSegment($path) : null;
        }
        unset($item);

        return $items;
    }

    private function lastSegment(string $fullPath): string
    {
        $segments = explode(' > ', $fullPath);

        return trim(end($segments));
    }

    public function updateCategory(int $id, ?string $categoryId): bool
    {
        $table = $this->schemaSqlHelper->table('product_cards');

        return (bool) $this->connection->update($table, [
            'category_id' => $categoryId,
        ], [
            'id' => $id,
        ], [
            'category_id' => Types::STRING,
            'id' => Types::INTEGER,
        ]);
    }

    public function updateDimensions(int $id, ?float $weight, ?float $width, ?float $height, ?float $length): bool
    {
        $table = $this->schemaSqlHelper->table('product_cards');

        return (bool) $this->connection->update($table, [
            'weight' => $weight,
            'width' => $width,
            'height' => $height,
            'length' => $length,
        ], [
            'id' => $id,
        ], [
            'weight' => Types::DECIMAL,
            'width' => Types::DECIMAL,
            'height' => Types::DECIMAL,
            'length' => Types::DECIMAL,
            'id' => Types::INTEGER,
        ]);
    }
}
