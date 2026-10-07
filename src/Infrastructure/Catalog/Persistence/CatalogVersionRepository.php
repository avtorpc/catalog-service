<?php

namespace App\Infrastructure\Catalog\Persistence;

use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;

final class CatalogVersionRepository
{
    public function __construct(
        private Connection $connection,
        private SchemaSqlHelper $schemaSqlHelper,
    ) {
    }

    public function getActiveCategoryTableName(): ?string
    {
        $table = $this->schemaSqlHelper->table('catalog_table_versions');

        $tableName = $this->connection->fetchOne("
            SELECT table_name
            FROM {$table}
            WHERE original_name = 'categories'
              AND is_active = true
            ORDER BY switched_at DESC NULLS LAST, id DESC
            LIMIT 1
        ");

        if ($tableName === false || $tableName === null) {
            return null;
        }

        $tableName = (string) $tableName;
        $this->assertSafeTableName($tableName);

        return $tableName;
    }

    private function assertSafeTableName(string $tableName): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \RuntimeException('Invalid active catalog table name');
        }
    }
}
