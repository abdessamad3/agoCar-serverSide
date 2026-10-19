<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: backfills historique_paiement, never captured by any
 *  migration. Legacy/superseded table (by `paiement`) -- kept because the
 *  entity mapping and HistoriquePaiementController still exist as a
 *  documented rollback path (see that controller's own @deprecated note);
 *  Version20260618162039 already conditionally backfills FROM this table. */
final class Version20260523050005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create historique_paiement table (never captured by any migration)';
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
    }

    private function fkExists(string $table, string $fk): bool
    {
        return (bool) $this->connection->executeQuery(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND CONSTRAINT_NAME='$fk' LIMIT 1"
        )->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('historique_paiement')) {
            $this->addSql("CREATE TABLE historique_paiement (id INT AUTO_INCREMENT NOT NULL, reservation_id INT NOT NULL, montant NUMERIC(10, 2) NOT NULL, date_paiement DATE NOT NULL COMMENT '(DC2Type:date_immutable)', mode_paiement VARCHAR(50) DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_710402ECB83297E7 (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->fkExists('historique_paiement', 'FK_710402ECB83297E7')) {
            $this->addSql('ALTER TABLE historique_paiement ADD CONSTRAINT FK_710402ECB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
