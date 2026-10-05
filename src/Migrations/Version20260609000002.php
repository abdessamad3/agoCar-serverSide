<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_log table to track sent daily report emails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS email_log (
            id               INT AUTO_INCREMENT NOT NULL,
            sent_at          DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            recipient_email  VARCHAR(255) NOT NULL,
            subject          VARCHAR(255) NOT NULL,
            total_alerts     INT NOT NULL DEFAULT 0,
            compliance_count INT NOT NULL DEFAULT 0,
            oil_count        INT NOT NULL DEFAULT 0,
            credit_count     INT NOT NULL DEFAULT 0,
            status           VARCHAR(20) NOT NULL DEFAULT \'sent\',
            error_message    LONGTEXT DEFAULT NULL,
            triggered_by     VARCHAR(30) NOT NULL DEFAULT \'scheduler\',
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX idx_email_log_sent_at ON email_log (sent_at)');
        $this->addSql('CREATE INDEX idx_email_log_status  ON email_log (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS email_log');
    }
}
