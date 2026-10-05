<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610130540 extends AbstractMigration
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
        // ── Block 1: missing tables (skip if already created by a previous partial run) ──────
        if (!$this->tableExists('conditions_contrat')) {
            $this->addSql('CREATE TABLE conditions_contrat (
                id INT NOT NULL,
                texte_francais LONGTEXT DEFAULT NULL,
                texte_arabe LONGTEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

            $this->addSql('CREATE TABLE deuxieme_chauffeur (
                id INT AUTO_INCREMENT NOT NULL,
                nom VARCHAR(100) NOT NULL,
                nationalite VARCHAR(50) DEFAULT NULL,
                cin VARCHAR(30) DEFAULT NULL,
                permis_conduite VARCHAR(50) DEFAULT NULL,
                permis_delivre_le DATE DEFAULT NULL,
                permis_delivre_a VARCHAR(100) DEFAULT NULL,
                date_naissance DATE DEFAULT NULL,
                adresse_maroc VARCHAR(255) DEFAULT NULL,
                telephone INT DEFAULT NULL,
                adresse_etranger VARCHAR(255) DEFAULT NULL,
                telephone_etranger VARCHAR(30) DEFAULT NULL,
                passeport VARCHAR(30) DEFAULT NULL,
                passeport_delivre_le DATE DEFAULT NULL,
                passeport_delivre_a VARCHAR(100) DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

            $this->addSql('CREATE TABLE parametres_societe (
                id INT NOT NULL,
                raison_sociale VARCHAR(255) DEFAULT NULL,
                telephones JSON NOT NULL,
                adresse VARCHAR(500) DEFAULT NULL,
                rc VARCHAR(100) DEFAULT NULL,
                ice VARCHAR(100) DEFAULT NULL,
                if_fiscal VARCHAR(100) DEFAULT NULL,
                cnss VARCHAR(100) DEFAULT NULL,
                logo_path VARCHAR(500) DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // ── Block 2: achat_installment index rename (skip if already renamed) ────────────────
        if ($this->indexExists('achat_installment', 'IDX_achat_installment_achat')) {
            $this->addSql('ALTER TABLE achat_installment DROP FOREIGN KEY FK_achat_installment_achat');
            $this->addSql('DROP INDEX IDX_achat_installment_achat ON achat_installment');
            $this->addSql('CREATE INDEX idx_a5125bf06a31b9b8 ON achat_installment (achat_voiture_id)');
            $this->addSql('ALTER TABLE achat_installment ADD CONSTRAINT FK_A5125BF06A31B9B8 FOREIGN KEY (achat_voiture_id) REFERENCES achat_voiture (id) ON DELETE CASCADE');
        }

        // ── Block 3: client columns (skip if date_naissance already added) ───────────────────
        if (!$this->columnExists('client', 'date_naissance')) {
            $this->addSql("ALTER TABLE client
                ADD date_naissance DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
                ADD adresse_maroc VARCHAR(255) NOT NULL DEFAULT '',
                ADD adresse_etranger VARCHAR(255) DEFAULT NULL");
        }

        // ── Block 4: contrat redesign (skip if client_id already dropped) ────────────────────
        if ($this->columnExists('contrat', 'client_id')) {
            $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_6034999319EB6921');
            $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_60349993FC29C013');
            $this->addSql('DROP INDEX IDX_6034999319EB6921 ON contrat');
            $this->addSql('DROP INDEX IDX_60349993FC29C013 ON contrat');
            $this->addSql('ALTER TABLE contrat
                DROP client_id,
                DROP cree_par_id,
                ADD deuxieme_chauffeur_id INT DEFAULT NULL,
                ADD numero VARCHAR(30) DEFAULT NULL,
                ADD kilometrage_depart INT DEFAULT NULL,
                ADD kilometrage_retour INT DEFAULT NULL,
                ADD niveau_carburant_depart VARCHAR(30) DEFAULT NULL,
                ADD niveau_carburant_retour VARCHAR(30) DEFAULT NULL,
                ADD equipements LONGTEXT DEFAULT NULL,
                ADD dommages LONGTEXT DEFAULT NULL,
                ADD signature_client LONGTEXT DEFAULT NULL,
                ADD signature_deuxieme_chauffeur LONGTEXT DEFAULT NULL,
                ADD signature_societe LONGTEXT DEFAULT NULL,
                ADD lieu_livraison VARCHAR(255) DEFAULT NULL,
                ADD lieu_retour VARCHAR(255) DEFAULT NULL,
                ADD caution_montant DECIMAL(10,2) DEFAULT NULL,
                ADD franchise DECIMAL(10,2) DEFAULT NULL,
                ADD conditions_contrat_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_contrat_deuxieme_chauffeur FOREIGN KEY (deuxieme_chauffeur_id) REFERENCES deuxieme_chauffeur (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_603499939BCD5DAF FOREIGN KEY (conditions_contrat_id) REFERENCES conditions_contrat (id) ON DELETE SET NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_60349993F55AE19E ON contrat (numero)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_603499939BCD5DAF ON contrat (conditions_contrat_id)');
        }

        // ── Block 5: payment_attachment FK rename ────────────────────────────────────────────
        if ($this->fkExists('payment_attachment', 'FK_PA_PAYMENT')) {
            $this->addSql('ALTER TABLE payment_attachment DROP FOREIGN KEY FK_PA_PAYMENT');
            $this->addSql('DROP INDEX IDX_PA_PAYMENT ON payment_attachment');
            $this->addSql('CREATE INDEX IDX_4CA9586E4C3A3BB ON payment_attachment (payment_id)');
            $this->addSql('ALTER TABLE payment_attachment ADD CONSTRAINT FK_4CA9586E4C3A3BB FOREIGN KEY (payment_id) REFERENCES vehicle_credit_payment (id)');
        }

        // ── Block 6: refresh_token index rename ──────────────────────────────────────────────
        if ($this->indexExists('refresh_token', 'token')) {
            $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_refresh_token_utilisateur');
            $this->addSql('DROP INDEX token ON refresh_token');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F21955F37A13B ON refresh_token (token)');
            $this->addSql('DROP INDEX IDX_F3F169BF3E5A5B9B ON refresh_token');
            $this->addSql('CREATE INDEX IDX_C74F2195FB88E14F ON refresh_token (utilisateur_id)');
            $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_refresh_token_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        }

        // ── Block 7: reservation index cleanup ───────────────────────────────────────────────
        if ($this->indexExists('reservation', 'IDX_42C8495519EB6921')) {
            $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495519EB6921');
            $this->addSql('DROP INDEX IDX_42C8495519EB6921 ON reservation');
            $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        }
        if ($this->indexExists('reservation', 'idx_reservation_voiture')) {
            $this->addSql('DROP INDEX idx_reservation_voiture ON reservation');
        }

        // ── Block 8: suivi_technique index cleanup ───────────────────────────────────────────
        if ($this->indexExists('suivi_technique', 'idx_suivi_technique_date_fin')) {
            $this->addSql('DROP INDEX idx_suivi_technique_date_fin ON suivi_technique');
        }

        // ── Block 9: vehicle_credit FK/index rename ──────────────────────────────────────────
        if ($this->fkExists('vehicle_credit', 'FK_VC_INSTITUTION')) {
            $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_VC_INSTITUTION');
            $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_VC_VOITURE');
            $this->addSql('DROP INDEX IDX_VC_STATUS ON vehicle_credit');
            $this->addSql('ALTER TABLE vehicle_credit CHANGE down_payment down_payment NUMERIC(12, 2) NOT NULL, CHANGE interest_rate interest_rate NUMERIC(5, 2) NOT NULL, CHANGE status status VARCHAR(30) NOT NULL');
            $this->addSql('DROP INDEX IDX_VC_VOITURE ON vehicle_credit');
            $this->addSql('CREATE INDEX IDX_B51F6F2D181A8BA ON vehicle_credit (voiture_id)');
            $this->addSql('DROP INDEX IDX_VC_INSTITUTION ON vehicle_credit');
            $this->addSql('CREATE INDEX IDX_B51F6F2DB2A7B468 ON vehicle_credit (financial_institution_id)');
            $this->addSql('CREATE INDEX IDX_B51F6F2D2ADD6D8C ON vehicle_credit (supplier_id)');
            $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_B51F6F2D181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
            $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_B51F6F2D2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES fournisseur (id)');
            $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_B51F6F2DB2A7B468 FOREIGN KEY (financial_institution_id) REFERENCES financial_institution (id)');
            $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_VC_INSTITUTION FOREIGN KEY (financial_institution_id) REFERENCES financial_institution (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_VC_VOITURE FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE CASCADE');
        }

        // ── Block 10: vehicle_credit_document FK/index rename ────────────────────────────────
        if ($this->fkExists('vehicle_credit_document', 'FK_VCD_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_document DROP FOREIGN KEY FK_VCD_CREDIT');
            $this->addSql('ALTER TABLE vehicle_credit_document CHANGE document_type document_type VARCHAR(50) NOT NULL');
            $this->addSql('DROP INDEX IDX_VCD_CREDIT ON vehicle_credit_document');
            $this->addSql('CREATE INDEX IDX_574D761439B96AAB ON vehicle_credit_document (vehicle_credit_id)');
            $this->addSql('ALTER TABLE vehicle_credit_document ADD CONSTRAINT FK_574D761439B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
            $this->addSql('ALTER TABLE vehicle_credit_document ADD CONSTRAINT FK_VCD_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        }

        // ── Block 11: vehicle_credit_installment FK/index rename ─────────────────────────────
        if ($this->fkExists('vehicle_credit_installment', 'FK_VCI_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_installment DROP FOREIGN KEY FK_VCI_CREDIT');
            $this->addSql('ALTER TABLE vehicle_credit_installment CHANGE principal_amount principal_amount NUMERIC(12, 2) NOT NULL, CHANGE interest_amount interest_amount NUMERIC(12, 2) NOT NULL, CHANGE amount_paid amount_paid NUMERIC(12, 2) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL');
            $this->addSql('DROP INDEX IDX_VCI_CREDIT ON vehicle_credit_installment');
            $this->addSql('CREATE INDEX IDX_842EDF6C39B96AAB ON vehicle_credit_installment (vehicle_credit_id)');
            $this->addSql('ALTER TABLE vehicle_credit_installment ADD CONSTRAINT FK_842EDF6C39B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
            $this->addSql('ALTER TABLE vehicle_credit_installment ADD CONSTRAINT FK_VCI_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        }

        // ── Block 12: vehicle_credit_payment FK/index rename ─────────────────────────────────
        if ($this->fkExists('vehicle_credit_payment', 'FK_VCP_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_VCP_INSTALLMENT');
            $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_VCP_CREDIT');
            $this->addSql('DROP INDEX IDX_VCP_DATE ON vehicle_credit_payment');
            $this->addSql('ALTER TABLE vehicle_credit_payment CHANGE payment_type payment_type VARCHAR(30) NOT NULL, CHANGE payment_method payment_method VARCHAR(30) NOT NULL');
            $this->addSql('DROP INDEX IDX_VCP_CREDIT ON vehicle_credit_payment');
            $this->addSql('CREATE INDEX IDX_2C4A19D139B96AAB ON vehicle_credit_payment (vehicle_credit_id)');
            $this->addSql('DROP INDEX IDX_VCP_INSTALLMENT ON vehicle_credit_payment');
            $this->addSql('CREATE INDEX IDX_2C4A19D1F03B5436 ON vehicle_credit_payment (installment_id)');
            $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_2C4A19D139B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
            $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_2C4A19D1F03B5436 FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id)');
            $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_VCP_INSTALLMENT FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_VCP_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        }

        // ── Block 13: vehicle_credit_reminder FK/index rename ────────────────────────────────
        if ($this->fkExists('vehicle_credit_reminder', 'FK_VCR_CREDIT')) {
            $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_VCR_CREDIT');
            $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_VCR_INSTALLMENT');
            $this->addSql('DROP INDEX IDX_VCR_DATE ON vehicle_credit_reminder');
            $this->addSql('DROP INDEX IDX_VCR_STATUS ON vehicle_credit_reminder');
            $this->addSql('ALTER TABLE vehicle_credit_reminder CHANGE status status VARCHAR(20) NOT NULL');
            $this->addSql('DROP INDEX IDX_VCR_CREDIT ON vehicle_credit_reminder');
            $this->addSql('CREATE INDEX IDX_CF13B32239B96AAB ON vehicle_credit_reminder (vehicle_credit_id)');
            $this->addSql('DROP INDEX FK_VCR_INSTALLMENT ON vehicle_credit_reminder');
            $this->addSql('CREATE INDEX IDX_CF13B322F03B5436 ON vehicle_credit_reminder (installment_id)');
            $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_CF13B32239B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
            $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_CF13B322F03B5436 FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id)');
            $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_VCR_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_VCR_INSTALLMENT FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id) ON DELETE CASCADE');
        }

        // ── Block 14: vehicle_return_inspection FK/index rename ──────────────────────────────
        if ($this->indexExists('vehicle_return_inspection', 'UNIQ_vri_reservation')) {
            $this->addSql('ALTER TABLE vehicle_return_inspection DROP FOREIGN KEY FK_vri_inspected_by');
            $this->addSql('ALTER TABLE vehicle_return_inspection DROP FOREIGN KEY FK_vri_reservation');
            $this->addSql('DROP INDEX UNIQ_vri_reservation ON vehicle_return_inspection');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_22DD1D3FB83297E7 ON vehicle_return_inspection (reservation_id)');
            $this->addSql('DROP INDEX IDX_vri_inspected_by ON vehicle_return_inspection');
            $this->addSql('CREATE INDEX IDX_22DD1D3F475EA6BE ON vehicle_return_inspection (inspected_by_id)');
            $this->addSql('ALTER TABLE vehicle_return_inspection ADD CONSTRAINT FK_vri_inspected_by FOREIGN KEY (inspected_by_id) REFERENCES utilisateur (id)');
            $this->addSql('ALTER TABLE vehicle_return_inspection ADD CONSTRAINT FK_vri_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        }

        // ── Block 15: vignette index cleanup ─────────────────────────────────────────────────
        if ($this->indexExists('vignette', 'idx_vignette_date_limite')) {
            $this->addSql('DROP INDEX idx_vignette_date_limite ON vignette');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_603499939BCD5DAF');
        $this->addSql('DROP TABLE conditions_contrat');
        $this->addSql('DROP TABLE deuxieme_chauffeur');
        $this->addSql('DROP TABLE parametres_societe');
        $this->addSql('ALTER TABLE achat_installment DROP FOREIGN KEY FK_A5125BF06A31B9B8');
        $this->addSql('DROP INDEX idx_a5125bf06a31b9b8 ON achat_installment');
        $this->addSql('CREATE INDEX IDX_achat_installment_achat ON achat_installment (achat_voiture_id)');
        $this->addSql('ALTER TABLE achat_installment ADD CONSTRAINT FK_A5125BF06A31B9B8 FOREIGN KEY (achat_voiture_id) REFERENCES achat_voiture (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client DROP date_naissance, DROP adresse_maroc, DROP adresse_etranger');
        $this->addSql('DROP INDEX UNIQ_60349993F55AE19E ON contrat');
        $this->addSql('DROP INDEX UNIQ_603499939BCD5DAF ON contrat');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_60349993B83297E7');
        $this->addSql('ALTER TABLE contrat ADD client_id INT NOT NULL, ADD cree_par_id INT DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP deuxieme_chauffeur_id, DROP numero, DROP kilometrage_depart, DROP kilometrage_retour, DROP niveau_carburant_depart, DROP niveau_carburant_retour, DROP equipements, DROP dommages, DROP signature_client, DROP signature_deuxieme_chauffeur, DROP signature_societe, DROP lieu_livraison, DROP lieu_retour, DROP caution_montant, DROP franchise');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_6034999319EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_60349993FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_6034999319EB6921 ON contrat (client_id)');
        $this->addSql('CREATE INDEX IDX_60349993FC29C013 ON contrat (cree_par_id)');
        $this->addSql('DROP INDEX uniq_contrat_reservation ON contrat');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_60349993B83297E7 ON contrat (reservation_id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_60349993B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE payment_attachment DROP FOREIGN KEY FK_4CA9586E4C3A3BB');
        $this->addSql('ALTER TABLE payment_attachment DROP FOREIGN KEY FK_4CA9586E4C3A3BB');
        $this->addSql('DROP INDEX idx_4ca9586e4c3a3bb ON payment_attachment');
        $this->addSql('CREATE INDEX IDX_PA_PAYMENT ON payment_attachment (payment_id)');
        $this->addSql('ALTER TABLE payment_attachment ADD CONSTRAINT FK_4CA9586E4C3A3BB FOREIGN KEY (payment_id) REFERENCES vehicle_credit_payment (id)');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195FB88E14F');
        $this->addSql('DROP INDEX uniq_c74f21955f37a13b ON refresh_token');
        $this->addSql('CREATE UNIQUE INDEX token ON refresh_token (token)');
        $this->addSql('DROP INDEX idx_c74f2195fb88e14f ON refresh_token');
        $this->addSql('CREATE INDEX IDX_F3F169BF3E5A5B9B ON refresh_token (utilisateur_id)');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_42C8495519EB6921 ON reservation (client_id)');
        $this->addSql('CREATE INDEX idx_reservation_voiture ON reservation (voiture_id)');
        $this->addSql('CREATE INDEX idx_suivi_technique_date_fin ON suivi_technique (date_fin)');
        $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_B51F6F2D181A8BA');
        $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_B51F6F2D2ADD6D8C');
        $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_B51F6F2DB2A7B468');
        $this->addSql('DROP INDEX IDX_B51F6F2D2ADD6D8C ON vehicle_credit');
        $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_B51F6F2D181A8BA');
        $this->addSql('ALTER TABLE vehicle_credit DROP FOREIGN KEY FK_B51F6F2DB2A7B468');
        $this->addSql('ALTER TABLE vehicle_credit CHANGE down_payment down_payment NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, CHANGE interest_rate interest_rate NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, CHANGE status status VARCHAR(30) DEFAULT \'draft\' NOT NULL');
        $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_VC_INSTITUTION FOREIGN KEY (financial_institution_id) REFERENCES financial_institution (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_VC_VOITURE FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_VC_STATUS ON vehicle_credit (status)');
        $this->addSql('DROP INDEX idx_b51f6f2d181a8ba ON vehicle_credit');
        $this->addSql('CREATE INDEX IDX_VC_VOITURE ON vehicle_credit (voiture_id)');
        $this->addSql('DROP INDEX idx_b51f6f2db2a7b468 ON vehicle_credit');
        $this->addSql('CREATE INDEX IDX_VC_INSTITUTION ON vehicle_credit (financial_institution_id)');
        $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_B51F6F2D181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE vehicle_credit ADD CONSTRAINT FK_B51F6F2DB2A7B468 FOREIGN KEY (financial_institution_id) REFERENCES financial_institution (id)');
        $this->addSql('ALTER TABLE vehicle_credit_document DROP FOREIGN KEY FK_574D761439B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_document DROP FOREIGN KEY FK_574D761439B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_document CHANGE document_type document_type VARCHAR(50) DEFAULT \'other\' NOT NULL');
        $this->addSql('ALTER TABLE vehicle_credit_document ADD CONSTRAINT FK_VCD_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_574d761439b96aab ON vehicle_credit_document');
        $this->addSql('CREATE INDEX IDX_VCD_CREDIT ON vehicle_credit_document (vehicle_credit_id)');
        $this->addSql('ALTER TABLE vehicle_credit_document ADD CONSTRAINT FK_574D761439B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
        $this->addSql('ALTER TABLE vehicle_credit_installment DROP FOREIGN KEY FK_842EDF6C39B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_installment DROP FOREIGN KEY FK_842EDF6C39B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_installment CHANGE principal_amount principal_amount NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, CHANGE interest_amount interest_amount NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, CHANGE amount_paid amount_paid NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE vehicle_credit_installment ADD CONSTRAINT FK_VCI_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_842edf6c39b96aab ON vehicle_credit_installment');
        $this->addSql('CREATE INDEX IDX_VCI_CREDIT ON vehicle_credit_installment (vehicle_credit_id)');
        $this->addSql('ALTER TABLE vehicle_credit_installment ADD CONSTRAINT FK_842EDF6C39B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
        $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_2C4A19D139B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_2C4A19D1F03B5436');
        $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_2C4A19D139B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_payment DROP FOREIGN KEY FK_2C4A19D1F03B5436');
        $this->addSql('ALTER TABLE vehicle_credit_payment CHANGE payment_type payment_type VARCHAR(30) DEFAULT \'scheduled\' NOT NULL, CHANGE payment_method payment_method VARCHAR(30) DEFAULT \'bank_transfer\' NOT NULL');
        $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_VCP_INSTALLMENT FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_VCP_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_VCP_DATE ON vehicle_credit_payment (payment_date)');
        $this->addSql('DROP INDEX idx_2c4a19d139b96aab ON vehicle_credit_payment');
        $this->addSql('CREATE INDEX IDX_VCP_CREDIT ON vehicle_credit_payment (vehicle_credit_id)');
        $this->addSql('DROP INDEX idx_2c4a19d1f03b5436 ON vehicle_credit_payment');
        $this->addSql('CREATE INDEX IDX_VCP_INSTALLMENT ON vehicle_credit_payment (installment_id)');
        $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_2C4A19D139B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
        $this->addSql('ALTER TABLE vehicle_credit_payment ADD CONSTRAINT FK_2C4A19D1F03B5436 FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id)');
        $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_CF13B32239B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_CF13B322F03B5436');
        $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_CF13B32239B96AAB');
        $this->addSql('ALTER TABLE vehicle_credit_reminder DROP FOREIGN KEY FK_CF13B322F03B5436');
        $this->addSql('ALTER TABLE vehicle_credit_reminder CHANGE status status VARCHAR(20) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_VCR_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_VCR_INSTALLMENT FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_VCR_DATE ON vehicle_credit_reminder (reminder_date)');
        $this->addSql('CREATE INDEX IDX_VCR_STATUS ON vehicle_credit_reminder (status)');
        $this->addSql('DROP INDEX idx_cf13b322f03b5436 ON vehicle_credit_reminder');
        $this->addSql('CREATE INDEX FK_VCR_INSTALLMENT ON vehicle_credit_reminder (installment_id)');
        $this->addSql('DROP INDEX idx_cf13b32239b96aab ON vehicle_credit_reminder');
        $this->addSql('CREATE INDEX IDX_VCR_CREDIT ON vehicle_credit_reminder (vehicle_credit_id)');
        $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_CF13B32239B96AAB FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id)');
        $this->addSql('ALTER TABLE vehicle_credit_reminder ADD CONSTRAINT FK_CF13B322F03B5436 FOREIGN KEY (installment_id) REFERENCES vehicle_credit_installment (id)');
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP FOREIGN KEY FK_22DD1D3FB83297E7');
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP FOREIGN KEY FK_22DD1D3F475EA6BE');
        $this->addSql('DROP INDEX uniq_22dd1d3fb83297e7 ON vehicle_return_inspection');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_vri_reservation ON vehicle_return_inspection (reservation_id)');
        $this->addSql('DROP INDEX idx_22dd1d3f475ea6be ON vehicle_return_inspection');
        $this->addSql('CREATE INDEX IDX_vri_inspected_by ON vehicle_return_inspection (inspected_by_id)');
        $this->addSql('ALTER TABLE vehicle_return_inspection ADD CONSTRAINT FK_22DD1D3FB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_return_inspection ADD CONSTRAINT FK_22DD1D3F475EA6BE FOREIGN KEY (inspected_by_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX idx_vignette_date_limite ON vignette (date_limite)');
    }
}
