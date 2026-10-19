<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: backfills company_staff (ManyToMany join table, no dedicated
 *  entity class), never captured by any migration. Depends on company
 *  (Version20260523050001). Guarded, no-op on any database that already
 *  has it. */
final class Version20260523050002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create company_staff join table (never captured by any migration)';
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
        if (!$this->tableExists('company_staff')) {
            $this->addSql('CREATE TABLE company_staff (company_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_59F51F7979B1AD6 (company_id), INDEX IDX_59F51F7FB88E14F (utilisateur_id), PRIMARY KEY(company_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->fkExists('company_staff', 'FK_59F51F7979B1AD6')) {
            $this->addSql('ALTER TABLE company_staff ADD CONSTRAINT FK_59F51F7979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        }
        if (!$this->fkExists('company_staff', 'FK_59F51F7FB88E14F')) {
            $this->addSql('ALTER TABLE company_staff ADD CONSTRAINT FK_59F51F7FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
