<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vehicle Financing Management Module — financial_institution, vehicle_credit, installments, payments, attachments, documents, reminders';
    }

    public function up(Schema $schema): void
    {
        // ── Financial Institutions ─────────────────────────────────────────────
        $this->addSql('CREATE TABLE financial_institution (
            id              INT AUTO_INCREMENT NOT NULL,
            name            VARCHAR(150) NOT NULL,
            type            VARCHAR(50) NOT NULL COMMENT "bank|leasing|credit|manufacturer|other",
            contact_person  VARCHAR(100) DEFAULT NULL,
            phone           VARCHAR(30)  DEFAULT NULL,
            email           VARCHAR(150) DEFAULT NULL,
            address         LONGTEXT     DEFAULT NULL,
            notes           LONGTEXT     DEFAULT NULL,
            status          VARCHAR(20)  NOT NULL DEFAULT "active",
            created_at      DATETIME     NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // ── Vehicle Credits ────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE vehicle_credit (
            id                       INT AUTO_INCREMENT NOT NULL,
            voiture_id               INT NOT NULL,
            supplier_id              INT DEFAULT NULL,
            financial_institution_id INT DEFAULT NULL,
            contract_number          VARCHAR(100) DEFAULT NULL,
            vehicle_price            DECIMAL(12,2) NOT NULL,
            down_payment             DECIMAL(12,2) NOT NULL DEFAULT 0,
            financed_amount          DECIMAL(12,2) NOT NULL,
            interest_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0,
            duration_months          INT NOT NULL,
            monthly_installment      DECIMAL(12,2) NOT NULL,
            total_cost               DECIMAL(12,2) NOT NULL,
            start_date               DATE NOT NULL,
            end_date                 DATE NOT NULL,
            first_payment_date       DATE DEFAULT NULL,
            due_day                  INT DEFAULT NULL,
            remaining_balance        DECIMAL(12,2) NOT NULL,
            status                   VARCHAR(30) NOT NULL DEFAULT "draft",
            notes                    LONGTEXT DEFAULT NULL,
            purchase_invoice_number  VARCHAR(100) DEFAULT NULL,
            purchase_date            DATE DEFAULT NULL,
            created_at               DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at               DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_VC_VOITURE (voiture_id),
            INDEX IDX_VC_INSTITUTION (financial_institution_id),
            INDEX IDX_VC_STATUS (status),
            CONSTRAINT FK_VC_VOITURE FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE CASCADE,
            CONSTRAINT FK_VC_INSTITUTION FOREIGN KEY (financial_institution_id) REFERENCES financial_institution (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // ── Installments ───────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE vehicle_credit_installment (
            id                  INT AUTO_INCREMENT NOT NULL,
            vehicle_credit_id   INT NOT NULL,
            installment_number  INT NOT NULL,
            due_date            DATE NOT NULL,
            principal_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
            interest_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
            amount_due          DECIMAL(12,2) NOT NULL,
            amount_paid         DECIMAL(12,2) NOT NULL DEFAULT 0,
            remaining_amount    DECIMAL(12,2) NOT NULL,
            paid_at             DATE DEFAULT NULL,
            status              VARCHAR(20) NOT NULL DEFAULT "pending",
            created_at          DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_VCI_CREDIT (vehicle_credit_id),
            INDEX IDX_VCI_DUE_DATE (due_date),
            INDEX IDX_VCI_STATUS (status),
            CONSTRAINT FK_VCI_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // ── Payments ───────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE vehicle_credit_payment (
            id                  INT AUTO_INCREMENT NOT NULL,
            vehicle_credit_id   INT NOT NULL,
            installment_id      INT DEFAULT NULL,
            payment_type        VARCHAR(30) NOT NULL DEFAULT "scheduled",
            payment_method      VARCHAR(30) NOT NULL DEFAULT "bank_transfer",
            amount              DECIMAL(12,2) NOT NULL,
            payment_date        DATE NOT NULL,
            reference_number    VARCHAR(100) DEFAULT NULL,
            notes               LONGTEXT DEFAULT NULL,
            created_at          DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_VCP_CREDIT (vehicle_credit_id),
            INDEX IDX_VCP_INSTALLMENT (installment_id),
            INDEX IDX_VCP_DATE (payment_date),
            CONSTRAINT FK_VCP_CREDIT       FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE,
            CONSTRAINT FK_VCP_INSTALLMENT  FOREIGN KEY (installment_id)    REFERENCES vehicle_credit_installment (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // ── Payment Attachments ────────────────────────────────────────────────
        $this->addSql('CREATE TABLE payment_attachment (
            id          INT AUTO_INCREMENT NOT NULL,
            payment_id  INT NOT NULL,
            file_name   VARCHAR(255) NOT NULL,
            file_path   VARCHAR(500) NOT NULL,
            file_type   VARCHAR(50) DEFAULT NULL,
            uploaded_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_PA_PAYMENT (payment_id),
            CONSTRAINT FK_PA_PAYMENT FOREIGN KEY (payment_id) REFERENCES vehicle_credit_payment (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // ── Contract Documents ─────────────────────────────────────────────────
        $this->addSql('CREATE TABLE vehicle_credit_document (
            id                  INT AUTO_INCREMENT NOT NULL,
            vehicle_credit_id   INT NOT NULL,
            document_type       VARCHAR(50) NOT NULL DEFAULT "other",
            file_path           VARCHAR(500) NOT NULL,
            file_name           VARCHAR(255) DEFAULT NULL,
            notes               LONGTEXT DEFAULT NULL,
            uploaded_at         DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_VCD_CREDIT (vehicle_credit_id),
            CONSTRAINT FK_VCD_CREDIT FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // ── Reminders ──────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE vehicle_credit_reminder (
            id                  INT AUTO_INCREMENT NOT NULL,
            vehicle_credit_id   INT NOT NULL,
            installment_id      INT DEFAULT NULL,
            reminder_date       DATE NOT NULL,
            reminder_type       VARCHAR(30) NOT NULL,
            status              VARCHAR(20) NOT NULL DEFAULT "pending",
            sent_at             DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            created_at          DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_VCR_CREDIT (vehicle_credit_id),
            INDEX IDX_VCR_DATE (reminder_date),
            INDEX IDX_VCR_STATUS (status),
            CONSTRAINT FK_VCR_CREDIT      FOREIGN KEY (vehicle_credit_id) REFERENCES vehicle_credit (id) ON DELETE CASCADE,
            CONSTRAINT FK_VCR_INSTALLMENT FOREIGN KEY (installment_id)    REFERENCES vehicle_credit_installment (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS vehicle_credit_reminder');
        $this->addSql('DROP TABLE IF EXISTS vehicle_credit_document');
        $this->addSql('DROP TABLE IF EXISTS payment_attachment');
        $this->addSql('DROP TABLE IF EXISTS vehicle_credit_payment');
        $this->addSql('DROP TABLE IF EXISTS vehicle_credit_installment');
        $this->addSql('DROP TABLE IF EXISTS vehicle_credit');
        $this->addSql('DROP TABLE IF EXISTS financial_institution');
    }
}
