<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add resultat (inspection pass/fail outcome) to suivi_technique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE suivi_technique ADD resultat VARCHAR(30) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi_technique DROP resultat');
    }
}
