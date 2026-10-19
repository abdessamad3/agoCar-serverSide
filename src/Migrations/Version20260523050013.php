<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: vidange's maintenance-interval/receipt columns were never
 *  captured by any migration. Additive only. Guarded, no-op where already
 *  present. */
final class Version20260523050013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add vidange.intervalle_km/file_path/deleted_at (never captured by any migration)';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('vidange', 'intervalle_km')) {
            $this->addSql('ALTER TABLE vidange ADD intervalle_km INT DEFAULT NULL');
        }
        if (!$this->columnExists('vidange', 'file_path')) {
            $this->addSql('ALTER TABLE vidange ADD file_path VARCHAR(500) DEFAULT NULL');
        }
        if (!$this->columnExists('vidange', 'deleted_at')) {
            $this->addSql("ALTER TABLE vidange ADD deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
