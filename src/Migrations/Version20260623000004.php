<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create parametres table (if missing); add debt_block_threshold/debt_block_enabled';
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('parametres')) {
            $this->addSql("CREATE TABLE parametres (
                id INT AUTO_INCREMENT NOT NULL,
                bureau_id INT NOT NULL,
                company_name VARCHAR(150) DEFAULT NULL,
                trade_name VARCHAR(150) DEFAULT NULL,
                ice VARCHAR(20) DEFAULT NULL,
                rc VARCHAR(20) DEFAULT NULL,
                cnss VARCHAR(20) DEFAULT NULL,
                tax_id VARCHAR(20) DEFAULT NULL,
                address VARCHAR(255) DEFAULT NULL,
                city VARCHAR(100) DEFAULT NULL,
                region VARCHAR(100) DEFAULT NULL,
                postal_code VARCHAR(10) DEFAULT NULL,
                phone VARCHAR(30) DEFAULT NULL,
                email VARCHAR(150) DEFAULT NULL,
                website VARCHAR(255) DEFAULT NULL,
                whatsapp VARCHAR(30) DEFAULT NULL,
                logo VARCHAR(255) DEFAULT NULL,
                currency VARCHAR(5) DEFAULT 'MAD',
                language VARCHAR(5) DEFAULT 'fr',
                date_format VARCHAR(15) DEFAULT 'DD/MM/YYYY',
                timezone VARCHAR(50) DEFAULT 'Africa/Casablanca',
                distance_unit VARCHAR(5) DEFAULT 'km',
                vat_rate DOUBLE PRECISION DEFAULT 20 NOT NULL,
                vat_label VARCHAR(20) DEFAULT 'TVA',
                show_vat_breakdown TINYINT(1) DEFAULT 1 NOT NULL,
                min_duration INT DEFAULT 1 NOT NULL,
                max_duration INT DEFAULT 30 NOT NULL,
                advance_booking_limit INT DEFAULT 90 NOT NULL,
                default_pickup_time VARCHAR(5) DEFAULT '09:00',
                default_return_time VARCHAR(5) DEFAULT '09:00',
                grace_period DOUBLE PRECISION DEFAULT 1 NOT NULL,
                default_deposit DOUBLE PRECISION DEFAULT 2000 NOT NULL,
                late_return_fee DOUBLE PRECISION DEFAULT 100 NOT NULL,
                require_deposit TINYINT(1) DEFAULT 1 NOT NULL,
                allow_partial_payments TINYINT(1) DEFAULT 0 NOT NULL,
                auto_generate_contract TINYINT(1) DEFAULT 1 NOT NULL,
                auto_generate_invoice TINYINT(1) DEFAULT 1 NOT NULL,
                require_signature TINYINT(1) DEFAULT 0 NOT NULL,
                contract_footer_note LONGTEXT DEFAULT NULL,
                debt_block_threshold DOUBLE PRECISION DEFAULT 1000 NOT NULL,
                debt_block_enabled TINYINT(1) DEFAULT 1 NOT NULL,
                notif_new_reservation TINYINT(1) DEFAULT 1 NOT NULL,
                notif_contract_activated TINYINT(1) DEFAULT 1 NOT NULL,
                notif_return_overdue TINYINT(1) DEFAULT 1 NOT NULL,
                notif_payment_received TINYINT(1) DEFAULT 1 NOT NULL,
                notif_maintenance_due TINYINT(1) DEFAULT 1 NOT NULL,
                notif_insurance_expiry TINYINT(1) DEFAULT 1 NOT NULL,
                notif_inspection_due TINYINT(1) DEFAULT 1 NOT NULL,
                admin_alert_email VARCHAR(150) DEFAULT NULL,
                operations_email VARCHAR(150) DEFAULT NULL,
                finance_alert_email VARCHAR(150) DEFAULT NULL,
                UNIQUE INDEX UNIQ_parametres_bureau (bureau_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
            $this->addSql('ALTER TABLE parametres ADD CONSTRAINT FK_parametres_bureau FOREIGN KEY (bureau_id) REFERENCES bureau (id) ON DELETE CASCADE');
        } else {
            if (!$this->columnExists('parametres', 'debt_block_threshold')) {
                $this->addSql('ALTER TABLE parametres ADD debt_block_threshold DOUBLE PRECISION DEFAULT 1000 NOT NULL');
            }
            if (!$this->columnExists('parametres', 'debt_block_enabled')) {
                $this->addSql('ALTER TABLE parametres ADD debt_block_enabled TINYINT(1) DEFAULT 1 NOT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametres DROP COLUMN debt_block_threshold');
        $this->addSql('ALTER TABLE parametres DROP COLUMN debt_block_enabled');
    }
}
