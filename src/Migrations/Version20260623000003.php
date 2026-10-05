<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add client.cin_delivre_a — CIN place of issue, for consistency with the existing passport/licence place of issue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD cin_delivre_a VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP COLUMN cin_delivre_a');
    }
}
