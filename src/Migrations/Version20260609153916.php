<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609153916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes on reservation, voiture, notification, achat_installment';
    }

    public function up(Schema $schema): void
    {
        // reservation, voiture, notification indexes already exist in DB — only achat_installment is missing
        $this->addSql('CREATE INDEX idx_ai_status   ON achat_installment (status)');
        $this->addSql('CREATE INDEX idx_ai_due_date ON achat_installment (due_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ai_status   ON achat_installment');
        $this->addSql('DROP INDEX idx_ai_due_date ON achat_installment');
    }
}
