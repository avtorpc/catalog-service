<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261009160000 extends AbstractMigration
{
 public function getDescription():string {return 'Transfer existing chat tables to node, preserving IDs, rows and internal relations';}
 public function up(Schema $schema):void {
  $this->addSql('CREATE SCHEMA IF NOT EXISTS node');
  $this->addSql(<<<'SQL'
DO $$
DECLARE c record;
BEGIN
 IF to_regclass('catalog.conversations') IS NOT NULL THEN
  IF to_regclass('node.conversations') IS NOT NULL OR to_regclass('node.messages') IS NOT NULL THEN
   RAISE EXCEPTION 'Both chat schemas exist: reconcile them before migrating';
  END IF;
  FOR c IN SELECT conname FROM pg_constraint WHERE conrelid='catalog.conversations'::regclass AND contype='f' LOOP
   EXECUTE format('ALTER TABLE catalog.conversations DROP CONSTRAINT %I',c.conname);
  END LOOP;
  ALTER TABLE catalog.conversations SET SCHEMA node;
  IF to_regclass('catalog.messages') IS NOT NULL THEN ALTER TABLE catalog.messages SET SCHEMA node; END IF;
 END IF;
END $$
SQL);
 }
 public function down(Schema $schema):void {$this->throwIrreversibleMigrationException('Chat ownership is now node-service. Restore a coordinated backup.');}
}
