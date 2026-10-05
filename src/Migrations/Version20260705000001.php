<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add justificatif_name column to depense table for PDF/image receipt upload';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depense ADD justificatif_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depense DROP COLUMN justificatif_name');
    }
}
