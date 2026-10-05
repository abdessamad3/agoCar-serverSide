<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add client.cin_delivre_le — CIN issue date, for consistency with the existing passport/licence issue dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD cin_delivre_le DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP COLUMN cin_delivre_le');
    }
}
