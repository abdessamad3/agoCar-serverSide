<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manager_id FK to bureau table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bureau ADD COLUMN manager_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bureau ADD CONSTRAINT FK_bureau_manager FOREIGN KEY (manager_id) REFERENCES utilisateur(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_bureau_manager ON bureau(manager_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_bureau_manager ON bureau');
        $this->addSql('ALTER TABLE bureau DROP FOREIGN KEY IF EXISTS FK_bureau_manager');
        $this->addSql('ALTER TABLE bureau DROP COLUMN IF EXISTS manager_id');
    }
}
