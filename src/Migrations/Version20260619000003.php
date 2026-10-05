<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notes and cancelledAt (distinct from archivedAt/superseded) to assurance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE assurance ADD notes LONGTEXT DEFAULT NULL, ADD cancelled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assurance DROP notes, DROP cancelled_at');
    }
}
