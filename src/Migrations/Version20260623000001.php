<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create client_document table (if missing); add deleted_at for soft-delete on document replacement';
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    private function fkExists(string $table, string $fk): bool
    {
        return (bool) $this->connection->executeQuery(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND CONSTRAINT_NAME='$fk' LIMIT 1"
        )->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('client_document')) {
            $this->addSql('CREATE TABLE client_document (
                id INT AUTO_INCREMENT NOT NULL,
                client_id INT NOT NULL,
                document_type VARCHAR(30) NOT NULL DEFAULT \'autre\',
                document_name VARCHAR(255) DEFAULT NULL,
                original_name VARCHAR(255) DEFAULT NULL,
                uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_client_document_client (client_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            if (!$this->fkExists('client_document', 'FK_client_document_client')) {
                $this->addSql('ALTER TABLE client_document ADD CONSTRAINT FK_client_document_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
            }
        } else {
            // Table already exists — just add the missing column
            if (!$this->columnExists('client_document', 'deleted_at')) {
                $this->addSql('ALTER TABLE client_document ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document DROP FOREIGN KEY FK_client_document_client');
        $this->addSql('DROP TABLE client_document');
    }
}
