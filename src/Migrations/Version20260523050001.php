<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 12.7: backfills a migration for a table that has existed in every
 * real deployment since the project's original (non-migration) bootstrap,
 * but was never captured by any migration -- a fresh database could not
 * create it. Guarded so it is a no-op against any database that already
 * has it (every existing deployment).
 */
final class Version20260523050001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create company table (never captured by any migration)';
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
        if (!$this->tableExists('company')) {
            $this->addSql("CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, manager_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, logo VARCHAR(255) DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', edit_au DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_4FBF094F783E3463 (manager_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->fkExists('company', 'FK_4FBF094F783E3463')) {
            $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_4FBF094F783E3463 FOREIGN KEY (manager_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op: this table predates migration tracking in
        // every real deployment. Reversing it would drop live data.
    }
}
