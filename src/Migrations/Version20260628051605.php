<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628051605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
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

    private function fkExists(string $table, string $fk): bool
    {
        return (bool) $this->connection->executeQuery(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND CONSTRAINT_NAME='$fk' LIMIT 1"
        )->fetchOne();
    }

    public function up(Schema $schema): void
    {
        // ── Bureau ───────────────────────────────────────────────────────────
        if ($this->fkExists('bureau', 'FK_bureau_manager')) {
            $this->addSql('ALTER TABLE bureau DROP FOREIGN KEY FK_bureau_manager');
        }
        if (!$this->columnExists('bureau', 'telephone')) {
            $this->addSql('ALTER TABLE bureau ADD telephone VARCHAR(50) DEFAULT NULL');
        }
        if ($this->indexExists('bureau', 'idx_bureau_manager')) {
            $this->addSql('DROP INDEX idx_bureau_manager ON bureau');
        }
        if (!$this->indexExists('bureau', 'IDX_166FDEC4783E3463')) {
            $this->addSql('CREATE INDEX IDX_166FDEC4783E3463 ON bureau (manager_id)');
        }
        if (!$this->fkExists('bureau', 'FK_bureau_manager')) {
            $this->addSql('ALTER TABLE bureau ADD CONSTRAINT FK_bureau_manager FOREIGN KEY (manager_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        }

        // ── Client ───────────────────────────────────────────────────────────
        if ($this->fkExists('client', 'FK_C7440455A7B547F4')) {
            $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455A7B547F4');
        }
        // CHANGE is idempotent — adds DC2Type comment, same column name
        $this->addSql('ALTER TABLE client CHANGE date_naissance date_naissance DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE permis_delivre_le permis_delivre_le DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE passeport_delivre_le passeport_delivre_le DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE cin_expiration cin_expiration DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE passeport_expiration passeport_expiration DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE permis_expiration permis_expiration DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE cin_delivre_le cin_delivre_le DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        if ($this->indexExists('client', 'uniq_c7440455b0286280')) {
            $this->addSql('DROP INDEX uniq_c7440455b0286280 ON client');
        }
        if (!$this->indexExists('client', 'UNIQ_C7440455ABE530DA')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455ABE530DA ON client (cin)');
        }
        if ($this->indexExists('client', 'uniq_c7440455e0bc9856')) {
            $this->addSql('DROP INDEX uniq_c7440455e0bc9856 ON client');
        }
        if (!$this->indexExists('client', 'UNIQ_C744045569DAB86A')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C744045569DAB86A ON client (passeport)');
        }
        if ($this->indexExists('client', 'uniq_c7440455ca29f6a8')) {
            $this->addSql('DROP INDEX uniq_c7440455ca29f6a8 ON client');
        }
        if (!$this->indexExists('client', 'UNIQ_C74404553E122970')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C74404553E122970 ON client (permis_conduite)');
        }
        if ($this->indexExists('client', 'idx_c7440455a7b547f4')) {
            $this->addSql('DROP INDEX idx_c7440455a7b547f4 ON client');
        }
        if (!$this->indexExists('client', 'IDX_C744045532516FE2')) {
            $this->addSql('CREATE INDEX IDX_C744045532516FE2 ON client (bureau_id)');
        }
        if (!$this->fkExists('client', 'FK_C7440455A7B547F4')) {
            $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455A7B547F4 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        }

        // ── Client document ──────────────────────────────────────────────────
        // CHANGE is idempotent — just adds the DC2Type comment
        $this->addSql('ALTER TABLE client_document CHANGE deleted_at deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // ── Depense indexes ──────────────────────────────────────────────────
        foreach (['idx_depense_type_depense', 'idx_depense_voiture_type_datefin', 'idx_depense_date_fin', 'idx_depense_date_debut'] as $idx) {
            if ($this->indexExists('depense', $idx)) {
                $this->addSql("DROP INDEX $idx ON depense");
            }
        }

        // ── Paiement ─────────────────────────────────────────────────────────
        if ($this->fkExists('paiement', 'FK_paiement_reservation')) {
            $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_paiement_reservation');
        }
        if ($this->indexExists('paiement', 'idx_paiement_reservation')) {
            $this->addSql('DROP INDEX idx_paiement_reservation ON paiement');
        }
        if (!$this->indexExists('paiement', 'IDX_B1DC7A1EB83297E7')) {
            $this->addSql('CREATE INDEX IDX_B1DC7A1EB83297E7 ON paiement (reservation_id)');
        }
        if (!$this->fkExists('paiement', 'FK_paiement_reservation')) {
            $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_paiement_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE SET NULL');
        }

        // ── Parametres legacy columns ────────────────────────────────────────
        $drops = [];
        foreach (['signature_image', 'tampon_image', 'equipement_couts', 'carburant_couts'] as $col) {
            if ($this->columnExists('parametres', $col)) {
                $drops[] = "DROP $col";
            }
        }
        if ($drops) {
            $this->addSql('ALTER TABLE parametres ' . implode(', ', $drops));
        }

        // ── Vehicle delivery ─────────────────────────────────────────────────
        if ($this->columnExists('vehicle_delivery', 'lieu_livraison')) {
            $this->addSql('ALTER TABLE vehicle_delivery DROP lieu_livraison');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bureau DROP FOREIGN KEY FK_166FDEC4783E3463');
        $this->addSql('ALTER TABLE bureau DROP telephone');
        $this->addSql('DROP INDEX idx_166fdec4783e3463 ON bureau');
        $this->addSql('CREATE INDEX idx_bureau_manager ON bureau (manager_id)');
        $this->addSql('ALTER TABLE bureau ADD CONSTRAINT FK_166FDEC4783E3463 FOREIGN KEY (manager_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C744045532516FE2');
        $this->addSql('ALTER TABLE client CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE permis_delivre_le permis_delivre_le DATE DEFAULT NULL, CHANGE passeport_delivre_le passeport_delivre_le DATE DEFAULT NULL, CHANGE cin_delivre_le cin_delivre_le DATE DEFAULT NULL, CHANGE cin_expiration cin_expiration DATE DEFAULT NULL, CHANGE passeport_expiration passeport_expiration DATE DEFAULT NULL, CHANGE permis_expiration permis_expiration DATE DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_c74404553e122970 ON client');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455CA29F6A8 ON client (permis_conduite)');
        $this->addSql('DROP INDEX uniq_c7440455abe530da ON client');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455B0286280 ON client (cin)');
        $this->addSql('DROP INDEX idx_c744045532516fe2 ON client');
        $this->addSql('CREATE INDEX IDX_C7440455A7B547F4 ON client (bureau_id)');
        $this->addSql('DROP INDEX uniq_c744045569dab86a ON client');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455E0BC9856 ON client (passeport)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C744045532516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE client_document CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_depense_type_depense ON depense (type_depense)');
        $this->addSql('CREATE INDEX idx_depense_voiture_type_datefin ON depense (voiture_id, type_depense, date_fin)');
        $this->addSql('CREATE INDEX idx_depense_date_fin ON depense (date_fin)');
        $this->addSql('CREATE INDEX idx_depense_date_debut ON depense (date_debut)');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EB83297E7');
        $this->addSql('DROP INDEX idx_b1dc7a1eb83297e7 ON paiement');
        $this->addSql('CREATE INDEX idx_paiement_reservation ON paiement (reservation_id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parametres ADD signature_image LONGTEXT DEFAULT NULL, ADD tampon_image LONGTEXT DEFAULT NULL, ADD equipement_couts JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', ADD carburant_couts JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE vehicle_delivery ADD lieu_livraison VARCHAR(255) DEFAULT NULL');
    }
}
