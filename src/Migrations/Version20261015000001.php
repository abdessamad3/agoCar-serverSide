<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add possible-duplicate-client flag to client (CIN/passport dedup, phase 2)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD possible_duplicate_of_id INT DEFAULT NULL, ADD duplicate_resolved_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455B46D723F FOREIGN KEY (possible_duplicate_of_id) REFERENCES client (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C7440455B46D723F ON client (possible_duplicate_of_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455B46D723F');
        $this->addSql('DROP INDEX IDX_C7440455B46D723F ON client');
        $this->addSql('ALTER TABLE client DROP possible_duplicate_of_id, DROP duplicate_resolved_at');
    }
}
