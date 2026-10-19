<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: utilisateur's auth/security columns were never captured by
 *  any migration (mustChangePassword, failedPasswordAttempts, etc. -- all
 *  load-bearing for login/lockout logic today, per the compatibility
 *  audit). Additive only. Guarded, no-op where already present. */
final class Version20260523050010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add utilisateur auth/security columns (never captured by any migration)';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('utilisateur', 'telephone')) {
            $this->addSql('ALTER TABLE utilisateur ADD telephone VARCHAR(20) DEFAULT NULL');
        }
        if (!$this->columnExists('utilisateur', 'photo')) {
            $this->addSql('ALTER TABLE utilisateur ADD photo VARCHAR(255) DEFAULT NULL');
        }
        if (!$this->columnExists('utilisateur', 'actif')) {
            $this->addSql("ALTER TABLE utilisateur ADD actif TINYINT(1) DEFAULT 1 NOT NULL");
        }
        if (!$this->columnExists('utilisateur', 'must_change_password')) {
            $this->addSql("ALTER TABLE utilisateur ADD must_change_password TINYINT(1) DEFAULT 0 NOT NULL");
        }
        if (!$this->columnExists('utilisateur', 'last_password_change_at')) {
            $this->addSql("ALTER TABLE utilisateur ADD last_password_change_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        if (!$this->columnExists('utilisateur', 'failed_password_attempts')) {
            $this->addSql("ALTER TABLE utilisateur ADD failed_password_attempts INT DEFAULT 0 NOT NULL");
        }
        if (!$this->columnExists('utilisateur', 'password_locked_until')) {
            $this->addSql("ALTER TABLE utilisateur ADD password_locked_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        if (!$this->columnExists('utilisateur', 'last_activity_at')) {
            $this->addSql("ALTER TABLE utilisateur ADD last_activity_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
