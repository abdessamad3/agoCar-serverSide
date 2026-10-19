<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: depense.montant_paye/date_facture were never captured by
 *  any migration. Additive only. Guarded, no-op where already present. */
final class Version20260523050012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add depense.montant_paye/date_facture (never captured by any migration)';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('depense', 'montant_paye')) {
            $this->addSql('ALTER TABLE depense ADD montant_paye NUMERIC(10, 2) DEFAULT NULL');
        }
        if (!$this->columnExists('depense', 'date_facture')) {
            $this->addSql("ALTER TABLE depense ADD date_facture DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
