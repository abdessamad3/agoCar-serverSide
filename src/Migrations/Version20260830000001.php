<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add depense_id to damage for repair payment tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE damage ADD depense_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE damage DROP COLUMN depense_id');
    }
}
