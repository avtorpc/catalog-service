<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add JSONB attributes column to catalog categories';
    }

    public function up(Schema $schema): void
    {
        $schemaName = $_ENV['DB_SCHEMA'] ?? 'public';
        $table = "\"{$schemaName}\".\"categories\"";
        $versionsTable = "\"{$schemaName}\".\"catalog_table_versions\"";

        $this->addSql("
            ALTER TABLE {$table}
            ADD COLUMN IF NOT EXISTS attributes JSONB NOT NULL DEFAULT '{}'::jsonb
        ");

        $this->addSql("
            COMMENT ON COLUMN {$table}.attributes
            IS 'Произвольные структурированные свойства категории в формате JSONB'
        ");

        $this->addSql("
            DO $$
            DECLARE
                category_table_name text;
            BEGIN
                IF to_regclass('{$schemaName}.catalog_table_versions') IS NOT NULL THEN
                    FOR category_table_name IN
                        SELECT DISTINCT table_name
                        FROM {$versionsTable}
                        WHERE original_name = 'categories'
                    LOOP
                        EXECUTE format(
                            'ALTER TABLE %I.%I ADD COLUMN IF NOT EXISTS attributes JSONB NOT NULL DEFAULT ''{}''::jsonb',
                            '{$schemaName}',
                            category_table_name
                        );

                        EXECUTE format(
                            'COMMENT ON COLUMN %I.%I.attributes IS %L',
                            '{$schemaName}',
                            category_table_name,
                            'Произвольные структурированные свойства категории в формате JSONB'
                        );
                    END LOOP;
                END IF;
            END $$;
        ");
    }

    public function down(Schema $schema): void
    {
        $schemaName = $_ENV['DB_SCHEMA'] ?? 'public';
        $table = "\"{$schemaName}\".\"categories\"";
        $versionsTable = "\"{$schemaName}\".\"catalog_table_versions\"";

        $this->addSql("
            ALTER TABLE {$table}
            DROP COLUMN IF EXISTS attributes
        ");

        $this->addSql("
            DO $$
            DECLARE
                category_table_name text;
            BEGIN
                IF to_regclass('{$schemaName}.catalog_table_versions') IS NOT NULL THEN
                    FOR category_table_name IN
                        SELECT DISTINCT table_name
                        FROM {$versionsTable}
                        WHERE original_name = 'categories'
                          AND table_name <> 'categories'
                    LOOP
                        EXECUTE format(
                            'ALTER TABLE %I.%I DROP COLUMN IF EXISTS attributes',
                            '{$schemaName}',
                            category_table_name
                        );
                    END LOOP;
                END IF;
            END $$;
        ");
    }
}
