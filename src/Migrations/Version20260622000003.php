<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add client.prenom and client.email — both were already read/displayed throughout the frontend (client lists, reservations, contracts) but never had a backing column, so they were silently discarded on every save';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD prenom VARCHAR(100) DEFAULT NULL, ADD email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP COLUMN prenom, DROP COLUMN email');
    }
}
