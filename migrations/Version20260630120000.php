<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблица product_import_results для результатов импорта карточек товаров';
    }

    public function up(Schema $schema): void
    {
        $schemaName = $_ENV['DB_SCHEMA'] ?? 'public';
        $table = "\"{$schemaName}\".\"product_import_results\"";

        $this->addSql("
            CREATE TABLE {$table}
            (
                id BIGSERIAL PRIMARY KEY,

                import_session_id UUID NOT NULL,

                line_number INTEGER NOT NULL,

                nomenclature VARCHAR(500) NOT NULL,

                external_code VARCHAR(100),

                quantity NUMERIC(18, 2),

                unit VARCHAR(50),

                price NUMERIC(18, 2),

                category_id VARCHAR(100),

                match_percent NUMERIC(5, 2),

                full_path TEXT,

                status VARCHAR(20) NOT NULL DEFAULT 'pending',

                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT chk_product_import_status
                    CHECK (status IN ('pending', 'matched', 'unmatched'))
            )
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.import_session_id
            IS 'UUID сессии импорта'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.line_number
            IS 'Номер строки из исходного CSV'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.nomenclature
            IS 'Наименование товара (колонка 1)'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.external_code
            IS 'Внешний код клиента (колонка 2)'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.category_id
            IS 'ID узла дерева категорий (если найден)'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.match_percent
            IS 'Процент совпадения с категорией'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.full_path
            IS 'Полный путь до узла в дереве категорий'
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.status
            IS 'pending — не обработан, matched — совпал, unmatched — не найден'
        ");

        $this->addSql("
            CREATE INDEX idx_product_import_session
            ON {$table} (import_session_id)
        ");

        $this->addSql("
            CREATE INDEX idx_product_import_status
            ON {$table} (status)
        ");
    }

    public function down(Schema $schema): void
    {
        $schemaName = $_ENV['DB_SCHEMA'] ?? 'public';
        $table = "\"{$schemaName}\".\"product_import_results\"";

        $this->addSql("DROP TABLE IF EXISTS {$table} CASCADE");
    }
}
