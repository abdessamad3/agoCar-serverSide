<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: bureau.company_id was never added by any migration, despite
 *  being required (Company/Bureau association). Depends on company
 *  (Version20260523050001). Additive only -- adds a new nullable column;
 *  never renames or drops anything. Guarded, no-op on any database that
 *  already has it. */
final class Version20260523050003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add bureau.company_id (never captured by any migration)';
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

    private function indexExists(string $table, string $index): bool
    {
        return (bool) $this->connection->executeQuery(
            "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND INDEX_NAME='$index' LIMIT 1"
        )->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('bureau', 'company_id')) {
            $this->addSql('ALTER TABLE bureau ADD company_id INT DEFAULT NULL');
        }
        if (!$this->indexExists('bureau', 'IDX_166FDEC4979B1AD6')) {
            $this->addSql('CREATE INDEX IDX_166FDEC4979B1AD6 ON bureau (company_id)');
        }
        if (!$this->fkExists('bureau', 'FK_166FDEC4979B1AD6')) {
            $this->addSql('ALTER TABLE bureau ADD CONSTRAINT FK_166FDEC4979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
