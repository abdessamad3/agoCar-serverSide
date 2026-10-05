<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status, repaired_at, reparation_id to damage table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE damage ADD status VARCHAR(20) NOT NULL DEFAULT 'open'");
        $this->addSql('ALTER TABLE damage ADD repaired_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE damage ADD reparation_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE damage DROP status, DROP repaired_at, DROP reparation_id');
    }
}
