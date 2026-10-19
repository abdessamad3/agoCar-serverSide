<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: voiture's full set of catalog/compliance columns was never
 *  captured by any migration (immatriculation, vin, etc. -- all live,
 *  daily-used fields today, per the Angular/React compatibility audit).
 *  Additive only. Guarded, no-op on any database that already has them. */
final class Version20260523050009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: add voiture catalog/compliance columns (never captured by any migration)';
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

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('voiture', 'version')) {
            $this->addSql("ALTER TABLE voiture ADD version VARCHAR(60) DEFAULT NULL, ADD immatriculation VARCHAR(30) DEFAULT NULL, ADD vin VARCHAR(50) DEFAULT NULL, ADD transmission VARCHAR(30) DEFAULT NULL, ADD places INT DEFAULT NULL, ADD portes INT DEFAULT NULL, ADD puissance_cv INT DEFAULT NULL, ADD categorie VARCHAR(40) DEFAULT NULL, ADD prix_semaine NUMERIC(10, 2) DEFAULT NULL, ADD prix_mois NUMERIC(10, 2) DEFAULT NULL, ADD caution NUMERIC(10, 2) DEFAULT NULL, ADD date_achat DATE DEFAULT NULL, ADD date_expiration_assurance DATE DEFAULT NULL, ADD date_expiration_vignette DATE DEFAULT NULL, ADD date_expiration_visite DATE DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        if (!$this->indexExists('voiture', 'idx_voiture_status')) {
            $this->addSql('CREATE INDEX idx_voiture_status ON voiture (voiture_status)');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
