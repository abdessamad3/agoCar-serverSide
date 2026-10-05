<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add file_path to paiement_depense for receipt upload';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement_depense ADD file_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement_depense DROP COLUMN file_path');
    }
}
