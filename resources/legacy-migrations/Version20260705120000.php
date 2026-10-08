<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблица product_cards — карточки товаров после импорта';
    }

    public function up(Schema $schema): void
    {
        $schemaName = $_ENV['DB_SCHEMA'] ?? 'public';
        $table = "\"{$schemaName}\".\"product_cards\"";

        $this->addSql("
            CREATE TABLE {$table}
            (
                id BIGSERIAL PRIMARY KEY,

                category_id VARCHAR(100),

                external_code VARCHAR(100),

                supplier_id UUID,

                stock_id VARCHAR(100),

                name VARCHAR(500) NOT NULL,

                slug VARCHAR(500) NOT NULL,

                quantity NUMERIC(18, 2),

                unit VARCHAR(50),

                price NUMERIC(18, 2),

                weight NUMERIC(10, 2),

                width NUMERIC(10, 2),

                height NUMERIC(10, 2),

                length NUMERIC(10, 2),

                currency VARCHAR(3),

                image_url TEXT,

                import_session_id UUID,

                status VARCHAR(20) NOT NULL DEFAULT 'active',

                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.category_id
            IS 'external_id из дерева категорий (catalog.*)'
        ");
        $this->addSql("
            COMMENT ON COLUMN {$table}.external_code
            IS 'Код товара из файла поставщика'
        ");
        $this->addSql("
            COMMENT ON COLUMN {$table}.supplier_id
            IS 'Идентификатор поставщика'
        ");
        $this->addSql("
            COMMENT ON COLUMN {$table}.stock_id
            IS 'Идентификатор склада поставщика'
        ");
        $this->addSql("
            COMMENT ON COLUMN {$table}.slug
            IS 'slug(external_code, stock_id) — уникальная ссылка'
        ");
        $this->addSql("
            COMMENT ON COLUMN {$table}.import_session_id
            IS 'UUID сессии импорта'
        ");
        $this->addSql("
            COMMENT ON COLUMN {$table}.status
            IS 'active — категория привязана И quantity > 0'
        ");

        $this->addSql("
            CREATE UNIQUE INDEX idx_product_cards_product
            ON {$table} (supplier_id, stock_id, external_code)
        ");
        $this->addSql("
            CREATE UNIQUE INDEX idx_product_cards_slug
            ON {$table} (slug)
        ");
        $this->addSql("
            CREATE INDEX idx_product_cards_category
            ON {$table} (category_id)
        ");
    }

    public function down(Schema $schema): void
    {
        $schemaName = $_ENV['DB_SCHEMA'] ?? 'public';
        $table = "\"{$schemaName}\".\"product_cards\"";

        $this->addSql("DROP TABLE IF EXISTS {$table} CASCADE");
    }
}
