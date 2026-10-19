<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: backfills voiture_image, never captured by any migration. */
final class Version20260523050008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create voiture_image table (never captured by any migration)';
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
        if (!$this->tableExists('voiture_image')) {
            $this->addSql("CREATE TABLE voiture_image (id INT AUTO_INCREMENT NOT NULL, voiture_id INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, position INT NOT NULL, cree_au DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_E890A1A8181A8BA (voiture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->fkExists('voiture_image', 'FK_E890A1A8181A8BA')) {
            $this->addSql('ALTER TABLE voiture_image ADD CONSTRAINT FK_E890A1A8181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
