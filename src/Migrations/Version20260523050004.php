<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: backfills activity_log, never captured by any migration. */
final class Version20260523050004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create activity_log table (never captured by any migration)';
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
        if (!$this->tableExists('activity_log')) {
            $this->addSql("CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, bureau_id INT DEFAULT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT NOT NULL, action VARCHAR(20) NOT NULL, old_data JSON DEFAULT NULL COMMENT '(DC2Type:json)', new_data JSON DEFAULT NULL COMMENT '(DC2Type:json)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ip_address VARCHAR(45) DEFAULT NULL, INDEX IDX_FD06F647A76ED395 (user_id), INDEX IDX_FD06F64732516FE2 (bureau_id), INDEX idx_entity (entity_type, entity_id), INDEX idx_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->fkExists('activity_log', 'FK_FD06F647A76ED395')) {
            $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        }
        if (!$this->fkExists('activity_log', 'FK_FD06F64732516FE2')) {
            $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F64732516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
