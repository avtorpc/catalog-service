<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261009100000 extends AbstractMigration
{
 public function getDescription(): string { return 'PDF files attached to resumes'; }
 public function up(Schema $schema): void { $s=$this->connection->quoteSingleIdentifier($_ENV['DB_SCHEMA']??'catalog');$this->addSql("ALTER TABLE {$s}.resumes ADD COLUMN pdf_original_name VARCHAR(255), ADD COLUMN pdf_storage_name VARCHAR(255), ADD COLUMN pdf_mime_type VARCHAR(100), ADD COLUMN pdf_size BIGINT CHECK(pdf_size IS NULL OR pdf_size>0)"); }
 public function down(Schema $schema): void { $s=$this->connection->quoteSingleIdentifier($_ENV['DB_SCHEMA']??'catalog');$this->addSql("ALTER TABLE {$s}.resumes DROP COLUMN pdf_original_name, DROP COLUMN pdf_storage_name, DROP COLUMN pdf_mime_type, DROP COLUMN pdf_size"); }
}
