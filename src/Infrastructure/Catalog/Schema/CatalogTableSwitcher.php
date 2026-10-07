<?php

namespace App\Infrastructure\Catalog\Schema;

use Doctrine\DBAL\Connection;

final class CatalogTableSwitcher
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * 5. Финализируем таблицу (регистрация версии)
     */
    public function registerVersion(string $tableName, int $rowsCount): void
    {
        $this->connection->beginTransaction();

        try {
            // 1. Деактивируем текущую активную версию
            $this->connection->executeStatement(
                "
                UPDATE catalog.catalog_table_versions
                SET
                    status = 'inactive',
                    is_active = false,
                    updated_at = NOW()
                WHERE is_active = true
                "
            );

            // 2. Обновляем текущую таблицу как активную
            $affected = $this->connection->executeStatement(
                "
                UPDATE catalog.catalog_table_versions
                SET
                    status = 'active',
                    is_active = true,
                    switched_at = NOW(),
                    rows_count = :rows_count,
                    updated_at = NOW()
                WHERE table_name = :table_name
                ",
                [
                    'table_name' => $tableName,
                    'rows_count' => $rowsCount,
                ]
            );

            // 3. Если записи нет — создаём новую
            if ($affected === 0) {
                $this->connection->executeStatement(
                    "
                    INSERT INTO catalog.catalog_table_versions
                        (table_name, original_name, version, status, is_active, switched_at, rows_count, created_at, updated_at)
                    VALUES
                        (:table_name, 'categories', :version, 'active', true, NOW(), :rows_count, NOW(), NOW())
                    ",
                    [
                        'table_name' => $tableName,
                        'version' => $this->extractVersion($tableName),
                        'rows_count' => $rowsCount,
                    ]
                );
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function extractVersion(string $tableName): string
    {
        // catalog_20260622_153240 → 20260622_153240
        if (preg_match('/_(\d{8}_\d{6})$/', $tableName, $m)) {
            return $m[1];
        }

        return 'unknown';
    }
}
