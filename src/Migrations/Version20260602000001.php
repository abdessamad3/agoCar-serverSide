<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change reservation.date_debut and date_fin from DATE to DATETIME';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reservation CHANGE date_debut date_debut DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE date_fin date_fin DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reservation CHANGE date_debut date_debut DATE NOT NULL COMMENT '(DC2Type:date_immutable)', CHANGE date_fin date_fin DATE NOT NULL COMMENT '(DC2Type:date_immutable)'");
    }
}
