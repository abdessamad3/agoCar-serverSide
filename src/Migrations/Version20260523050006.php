<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: backfills notification, never captured by any migration. */
final class Version20260523050006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create notification table (never captured by any migration)';
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
        if (!$this->tableExists('notification')) {
            $this->addSql("CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(200) NOT NULL, message VARCHAR(500) NOT NULL, type VARCHAR(50) NOT NULL, source_type VARCHAR(50) NOT NULL, source_id INT NOT NULL, priority VARCHAR(20) NOT NULL, deep_link VARCHAR(255) DEFAULT NULL, read_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_BF5476CAA76ED395 (user_id), INDEX idx_notif_user_read (user_id, read_at), UNIQUE INDEX uniq_notif_source (user_id, source_type, source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->fkExists('notification', 'FK_BF5476CAA76ED395')) {
            $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
