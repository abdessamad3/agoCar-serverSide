<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611094801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
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
        // ── Block A: contract_extension ──────────────────────────────────────
        if (!$this->tableExists('contract_extension')) {
            $this->addSql('CREATE TABLE contract_extension (id INT AUTO_INCREMENT NOT NULL, contrat_id INT NOT NULL, date_from DATE NOT NULL, date_to DATE NOT NULL, notes LONGTEXT DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B22065E11823061F (contrat_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->fkExists('contract_extension', 'FK_B22065E11823061F')) {
            $this->addSql('ALTER TABLE contract_extension ADD CONSTRAINT FK_B22065E11823061F FOREIGN KEY (contrat_id) REFERENCES contrat (id) ON DELETE CASCADE');
        }

        // ── Block B: damage ──────────────────────────────────────────────────
        if (!$this->tableExists('damage')) {
            $this->addSql('CREATE TABLE damage (id INT AUTO_INCREMENT NOT NULL, vehicle_delivery_id INT DEFAULT NULL, return_inspection_id INT DEFAULT NULL, zone VARCHAR(30) NOT NULL, description LONGTEXT DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, severity VARCHAR(30) DEFAULT \'scratch\' NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_11C8546C9C281457 (vehicle_delivery_id), INDEX IDX_11C8546C744A1FD4 (return_inspection_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->fkExists('damage', 'FK_11C8546C9C281457')) {
            $this->addSql('ALTER TABLE damage ADD CONSTRAINT FK_11C8546C9C281457 FOREIGN KEY (vehicle_delivery_id) REFERENCES vehicle_delivery (id) ON DELETE CASCADE');
        }
        if (!$this->fkExists('damage', 'FK_11C8546C744A1FD4')) {
            $this->addSql('ALTER TABLE damage ADD CONSTRAINT FK_11C8546C744A1FD4 FOREIGN KEY (return_inspection_id) REFERENCES vehicle_return_inspection (id) ON DELETE CASCADE');
        }

        // ── Block C: vehicle_delivery ────────────────────────────────────────
        if (!$this->tableExists('vehicle_delivery')) {
            $this->addSql('CREATE TABLE vehicle_delivery (id INT AUTO_INCREMENT NOT NULL, reservation_id INT NOT NULL, fuel_level_out VARCHAR(30) DEFAULT \'vide\' NOT NULL, mileage_out INT DEFAULT NULL, has_extincteur TINYINT(1) DEFAULT 0 NOT NULL, has_lavage TINYINT(1) DEFAULT 0 NOT NULL, has_plaque_depannage TINYINT(1) DEFAULT 0 NOT NULL, has_cric TINYINT(1) DEFAULT 0 NOT NULL, has_gilet TINYINT(1) DEFAULT 0 NOT NULL, has_roue_secours TINYINT(1) DEFAULT 0 NOT NULL, has_siege_bebe TINYINT(1) DEFAULT 0 NOT NULL, equipement_notes LONGTEXT DEFAULT NULL, delivery_notes LONGTEXT DEFAULT NULL, signature_client_depart LONGTEXT DEFAULT NULL, signature_deuxieme_chauffeur LONGTEXT DEFAULT NULL, signature_societe_depart LONGTEXT DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_EA93E384B83297E7 (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->fkExists('vehicle_delivery', 'FK_EA93E384B83297E7')) {
            $this->addSql('ALTER TABLE vehicle_delivery ADD CONSTRAINT FK_EA93E384B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        }

        // ── Block D: client new columns ──────────────────────────────────────
        if (!$this->columnExists('client', 'lieu_naissance')) {
            $this->addSql('ALTER TABLE client ADD lieu_naissance VARCHAR(100) DEFAULT NULL, ADD telephone_etranger VARCHAR(30) DEFAULT NULL, ADD permis_delivre_le DATE DEFAULT NULL, ADD permis_delivre_a VARCHAR(100) DEFAULT NULL, ADD passeport_delivre_le DATE DEFAULT NULL, ADD passeport_delivre_a VARCHAR(100) DEFAULT NULL');
        }

        // ── Block E: contrat — drop FKs then redesign ────────────────────────
        if ($this->fkExists('contrat', 'FK_contrat_deuxieme_chauffeur')) {
            $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_contrat_deuxieme_chauffeur');
        }
        if ($this->fkExists('contrat', 'FK_603499939BCD5DAF')) {
            $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_603499939BCD5DAF');
        }
        if ($this->indexExists('contrat', 'UNIQ_603499939BCD5DAF')) {
            $this->addSql('DROP INDEX UNIQ_603499939BCD5DAF ON contrat');
        }
        if (!$this->columnExists('contrat', 'has_caution')) {
            $this->addSql('ALTER TABLE contrat ADD has_caution TINYINT(1) DEFAULT 0 NOT NULL, ADD fait_a VARCHAR(100) DEFAULT NULL, ADD signed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD prix_par_jour_snapshot NUMERIC(10, 2) DEFAULT NULL, ADD nb_jours_factures INT DEFAULT NULL, ADD remise NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, ADD taxes NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, DROP deuxieme_chauffeur_id, DROP kilometrage_depart, DROP kilometrage_retour, DROP niveau_carburant_depart, DROP niveau_carburant_retour, DROP equipements, DROP dommages, DROP signature_client, DROP signature_deuxieme_chauffeur, DROP signature_societe, DROP lieu_livraison, DROP lieu_retour');
        }

        // ── Block F: deuxieme_chauffeur columns (skip if already full schema) ─
        if (!$this->columnExists('deuxieme_chauffeur', 'nationalite')) {
            $this->addSql('ALTER TABLE deuxieme_chauffeur ADD nationalite VARCHAR(50) DEFAULT NULL, ADD permis_delivre_le DATE DEFAULT NULL, ADD permis_delivre_a VARCHAR(100) DEFAULT NULL, ADD adresse_maroc VARCHAR(255) DEFAULT NULL, ADD telephone INT DEFAULT NULL, ADD adresse_etranger VARCHAR(255) DEFAULT NULL, ADD telephone_etranger VARCHAR(30) DEFAULT NULL, ADD passeport VARCHAR(30) DEFAULT NULL, ADD passeport_delivre_le DATE DEFAULT NULL, ADD passeport_delivre_a VARCHAR(100) DEFAULT NULL');
        }

        // ── Block G: reservation new columns + FK + index ────────────────────
        if (!$this->columnExists('reservation', 'deuxieme_chauffeur_id')) {
            $this->addSql('ALTER TABLE reservation ADD deuxieme_chauffeur_id INT DEFAULT NULL, ADD lieu_livraison VARCHAR(255) DEFAULT NULL, ADD lieu_retour VARCHAR(255) DEFAULT NULL, ADD prix_par_jour NUMERIC(10, 2) DEFAULT NULL');
        }
        if (!$this->fkExists('reservation', 'FK_42C849559BCD5DAF')) {
            $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559BCD5DAF FOREIGN KEY (deuxieme_chauffeur_id) REFERENCES deuxieme_chauffeur (id)');
        }
        if (!$this->indexExists('reservation', 'UNIQ_42C849559BCD5DAF')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_42C849559BCD5DAF ON reservation (deuxieme_chauffeur_id)');
        }

        // ── Block H: vehicle_credit FK drops ─────────────────────────────────
        if ($this->fkExists('vehicle_credit', 'FK_VC_INSTITUTION')) {
            $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_VC_INSTITUTION');
        }
        if ($this->fkExists('vehicle_credit', 'FK_VC_VOITURE')) {
            $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_VC_VOITURE');
        }

        // ── Block I: vehicle_credit_* FK drops ───────────────────────────────
        if ($this->fkExists('vehicle_credit_document', 'FK_VCD_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_document DROP FOREIGN KEY FK_VCD_CREDIT');
        }
        if ($this->fkExists('vehicle_credit_installment', 'FK_VCI_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_installment DROP FOREIGN KEY FK_VCI_CREDIT');
        }
        if ($this->fkExists('vehicle_credit_payment', 'FK_VCP_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_VCP_CREDIT');
        }
        if ($this->fkExists('vehicle_credit_payment', 'FK_VCP_INSTALLMENT')) {
            $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_VCP_INSTALLMENT');
        }
        if ($this->fkExists('vehicle_credit_reminder', 'FK_VCR_INSTALLMENT')) {
            $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_VCR_INSTALLMENT');
        }
        if ($this->fkExists('vehicle_credit_reminder', 'FK_VCR_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_VCR_CREDIT');
        }

        // ── Block J: vehicle_return_inspection new columns ───────────────────
        if (!$this->columnExists('vehicle_return_inspection', 'fuel_level_in')) {
            $this->addSql('ALTER TABLE vehicle_return_inspection ADD fuel_level_in VARCHAR(30) DEFAULT NULL, ADD fuel_charge NUMERIC(10, 2) DEFAULT NULL, ADD late_charge NUMERIC(10, 2) DEFAULT NULL, ADD damage_charge NUMERIC(10, 2) DEFAULT NULL, ADD signature_client_retour LONGTEXT DEFAULT NULL, ADD signature_societe_retour LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract_extension DROP FOREIGN KEY FK_B22065E11823061F');
        $this->addSql('ALTER TABLE damage DROP FOREIGN KEY FK_11C8546C9C281457');
        $this->addSql('ALTER TABLE damage DROP FOREIGN KEY FK_11C8546C744A1FD4');
        $this->addSql('ALTER TABLE vehicle_delivery DROP FOREIGN KEY FK_EA93E384B83297E7');
        $this->addSql('DROP TABLE contract_extension');
        $this->addSql('DROP TABLE damage');
        $this->addSql('DROP TABLE vehicle_delivery');
        $this->addSql('ALTER TABLE client DROP lieu_naissance, DROP telephone_etranger, DROP permis_delivre_le, DROP permis_delivre_a, DROP passeport_delivre_le, DROP passeport_delivre_a');
        $this->addSql('ALTER TABLE contrat ADD kilometrage_depart INT DEFAULT NULL, ADD kilometrage_retour INT DEFAULT NULL, ADD niveau_carburant_depart VARCHAR(30) DEFAULT \'vide\' NOT NULL, ADD niveau_carburant_retour VARCHAR(30) DEFAULT \'vide\' NOT NULL, ADD equipements JSON DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', ADD dommages JSON DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', ADD signature_client LONGTEXT DEFAULT NULL, ADD signature_deuxieme_chauffeur LONGTEXT DEFAULT NULL, ADD signature_societe LONGTEXT DEFAULT NULL, ADD lieu_livraison VARCHAR(255) DEFAULT NULL, ADD lieu_retour VARCHAR(255) DEFAULT NULL, DROP has_caution, DROP fait_a, DROP signed_at, DROP prix_par_jour_snapshot, DROP remise, DROP taxes, CHANGE nb_jours_factures deuxieme_chauffeur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_603499939BCD5DAF FOREIGN KEY (deuxieme_chauffeur_id) REFERENCES deuxieme_chauffeur (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_603499939BCD5DAF ON contrat (deuxieme_chauffeur_id)');
        $this->addSql('ALTER TABLE deuxieme_chauffeur DROP nationalite, DROP permis_delivre_le, DROP permis_delivre_a, DROP adresse_maroc, DROP telephone, DROP adresse_etranger, DROP telephone_etranger, DROP passeport, DROP passeport_delivre_le, DROP passeport_delivre_a');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559BCD5DAF');
        $this->addSql('DROP INDEX UNIQ_42C849559BCD5DAF ON reservation');
        $this->addSql('ALTER TABLE reservation DROP deuxieme_chauffeur_id, DROP lieu_livraison, DROP lieu_retour, DROP prix_par_jour');
        $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_VC_INSTITUTION FOREIGN KEY (financial_institution_id) REFERENCES financial_institution (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_VC_VOITURE FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_credit_document ADD CONSTRAINT FK_VCD_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_credit_installment ADD CONSTRAINT FK_VCI_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_VCP_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_VCP_INSTALLMENT FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_VCR_INSTALLMENT FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_VCR_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP fuel_level_in, DROP fuel_charge, DROP late_charge, DROP damage_charge, DROP signature_client_retour, DROP signature_societe_retour');
    }
}
