<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add estimated_cost to damage table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE damage ADD estimated_cost NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE damage DROP COLUMN estimated_cost');
    }
}
