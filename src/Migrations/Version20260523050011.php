<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: reservation's payment-tracking columns and performance
 *  indexes were never captured by any migration. Additive only. Guarded,
 *  no-op where already present. */
final class Version20260523050011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add reservation payment columns + indexes (never captured by any migration)';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) $this->connection->executeQuery(
            "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND INDEX_NAME='$index' LIMIT 1"
        )->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('reservation', 'montant_paye')) {
            $this->addSql("ALTER TABLE reservation ADD montant_paye NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
        }
        if (!$this->columnExists('reservation', 'mode_paiement')) {
            $this->addSql('ALTER TABLE reservation ADD mode_paiement VARCHAR(50) DEFAULT NULL');
        }
        if (!$this->columnExists('reservation', 'deleted_at')) {
            $this->addSql("ALTER TABLE reservation ADD deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        if (!$this->indexExists('reservation', 'idx_reservation_client')) {
            $this->addSql('CREATE INDEX idx_reservation_client ON reservation (client_id)');
        }
        if (!$this->indexExists('reservation', 'idx_reservation_date_debut')) {
            $this->addSql('CREATE INDEX idx_reservation_date_debut ON reservation (date_debut)');
        }
        if (!$this->indexExists('reservation', 'idx_reservation_date_fin')) {
            $this->addSql('CREATE INDEX idx_reservation_date_fin ON reservation (date_fin)');
        }
        if (!$this->indexExists('reservation', 'idx_res_status')) {
            $this->addSql('CREATE INDEX idx_res_status ON reservation (reservation_status)');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
