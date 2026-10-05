<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add file_paths JSON column to reparation, adblue and vidange tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reparation ADD file_paths JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE adblue ADD file_paths JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE vidange ADD file_paths JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reparation DROP COLUMN file_paths');
        $this->addSql('ALTER TABLE adblue DROP COLUMN file_paths');
        $this->addSql('ALTER TABLE vidange DROP COLUMN file_paths');
    }
}
