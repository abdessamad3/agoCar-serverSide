<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change client.telephone from INT to VARCHAR(20) — int rejected non-numeric input (e.g. leading zeros, spaces) and crashed client creation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client MODIFY telephone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client MODIFY telephone INT DEFAULT NULL');
    }
}
