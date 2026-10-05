<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618062725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add file_path to assurance and suivi_technique for document download support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assurance ADD file_path VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE suivi_technique ADD file_path VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assurance DROP file_path');
        $this->addSql('ALTER TABLE suivi_technique DROP file_path');
    }
}
