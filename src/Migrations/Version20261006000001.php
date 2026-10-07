<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add langue (preferred UI/email language) to utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE utilisateur ADD langue VARCHAR(2) NOT NULL DEFAULT 'fr'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP langue');
    }
}
