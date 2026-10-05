<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add client.bureau_id, set to the creating user\'s bureau — needed so bureau-scoped staff/managers can see clients they just created, instead of only clients with an existing reservation in their bureau';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD bureau_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455A7B547F4 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('CREATE INDEX IDX_C7440455A7B547F4 ON client (bureau_id)');
        // Backfill existing clients from their creator's bureau, so none go missing from bureau-scoped lists.
        $this->addSql('UPDATE client c JOIN utilisateur u ON u.id = c.cree_par_id SET c.bureau_id = u.bureau_id WHERE u.bureau_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455A7B547F4');
        $this->addSql('DROP INDEX IDX_C7440455A7B547F4 ON client');
        $this->addSql('ALTER TABLE client DROP COLUMN bureau_id');
    }
}
