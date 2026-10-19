<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: suivi_technique.depense_id (the join to the unified expense
 *  tracking introduced by Version20260616000002) was never captured by any
 *  migration -- that migration only migrates data assuming this column
 *  already exists. Additive only. Guarded, no-op where already present. */
final class Version20260523050014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add suivi_technique.depense_id (never captured by any migration)';
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
        if (!$this->columnExists('suivi_technique', 'depense_id')) {
            $this->addSql('ALTER TABLE suivi_technique ADD depense_id INT DEFAULT NULL');
        }
        if (!$this->fkExists('suivi_technique', 'FK_BE9B632F41D81563')) {
            $this->addSql('ALTER TABLE suivi_technique ADD CONSTRAINT FK_BE9B632F41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        }
        if (!$this->indexExists('suivi_technique', 'UNIQ_BE9B632F41D81563')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_BE9B632F41D81563 ON suivi_technique (depense_id)');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
