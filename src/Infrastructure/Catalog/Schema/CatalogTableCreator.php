<?php

namespace App\Infrastructure\Catalog\Schema;

use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;

final class CatalogTableCreator
{
    public function __construct(
        private Connection $connection,
        private SchemaSqlHelper $schemaSqlHelper,
    ) {
    }

    /**
     * Создает новую таблицу каталога.
     *
     * Пример:
     * catalog.catalog_20260622_141530
     */
    public function create(?string $prefix = null): string
    {
        $timestamp = (new \DateTimeImmutable())
            ->format('Ymd_His');

        $tablePrefix = $prefix ?? 'catalog';

        $tableName = $tablePrefix . '_' . $timestamp;

        $this->assertSafeName($tableName);

        /**
         * Полное имя таблицы со схемой.
         */
        $fullTableName = $this->schemaSqlHelper->table($tableName);

        /**
         * Загружаем SQL-шаблон.
         */
        $sql = file_get_contents(
            __DIR__ . '/../Sql/categories_template.sql'
        );

        if ($sql === false) {
            throw new \RuntimeException(
                'Unable to load categories_template.sql'
            );
        }

        /**
         * Подставляем имя таблицы и префикс
         * для constraint и index.
         */
        $sql = str_replace(
            [
                '{{table}}',
                '{{prefix}}',
            ],
            [
                $fullTableName,
                $tableName,
            ],
            $sql
        );

        /**
         * Выполняем DDL.
         */
        $this->connection->executeStatement($sql);

        return $tableName;
    }

    private function assertSafeName(string $tableName): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new \InvalidArgumentException(
                'Invalid table name: ' . $tableName
            );
        }
    }
}
