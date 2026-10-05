<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create error_log table — durable backend/frontend error history, separate from activity_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE error_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, source VARCHAR(20) NOT NULL, exception_class VARCHAR(255) DEFAULT NULL, message LONGTEXT NOT NULL, file VARCHAR(500) DEFAULT NULL, line INT DEFAULT NULL, trace LONGTEXT DEFAULT NULL, request_url VARCHAR(255) DEFAULT NULL, request_method VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ip_address VARCHAR(45) DEFAULT NULL, INDEX idx_error_log_created_at (created_at), INDEX idx_error_log_source (source), INDEX IDX_ERROR_LOG_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE error_log ADD CONSTRAINT FK_ERROR_LOG_USER FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE error_log DROP FOREIGN KEY FK_ERROR_LOG_USER');
        $this->addSql('DROP TABLE error_log');
    }
}
