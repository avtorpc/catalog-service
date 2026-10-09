<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261009190000 extends AbstractMigration
{
 public function getDescription():string{return 'Allow vacancy conversations from a profile without creating a fictitious resume';}
 public function up(Schema $schema):void {$this->addSql('ALTER TABLE catalog.applications ALTER COLUMN resume_id DROP NOT NULL');}
 public function down(Schema $schema):void {$this->throwIrreversibleMigrationException('Preserve applications started without a resume.');}
}
