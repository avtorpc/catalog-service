<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261008140000 extends AbstractMigration
{
 public function getDescription():string { return 'Mesto professional profiles, publications, conversations and qualification assessments'; }
 public function up(Schema $schema):void {
  $name=$this->connection->quoteSingleIdentifier($_ENV['DB_SCHEMA']??'catalog');
  $this->addSql("CREATE SCHEMA IF NOT EXISTS {$name}");
  $this->addSql("SET LOCAL search_path TO {$name}, pg_catalog, public");
  $queries=json_decode(file_get_contents(__DIR__.'/../resources/migrations/20261008/schema.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($queries as $query)$this->addSql($query);
 }
 public function down(Schema $schema):void { $this->throwIrreversibleMigrationException('Professional profiles and user documents must be preserved. Restore a backup instead.'); }
}
