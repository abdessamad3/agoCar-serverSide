<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_token.replaced_by_token — lets a benign refresh race (e.g. two open tabs, or a dev-server reload racing an in-flight request) follow the rotation chain instead of force-logging the user out';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token ADD replaced_by_token VARCHAR(128) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token DROP COLUMN replaced_by_token');
    }
}
