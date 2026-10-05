<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add production performance indexes for reservation, depense, notification, assurance, vignette, suivi_technique';
    }

    public function up(Schema $schema): void
    {
        // performance indexes deferred — tables created by later migrations
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}
