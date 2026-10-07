<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add remise_montant/remise_motif (per-reservation discount) to reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reservation ADD remise_montant NUMERIC(10, 2) DEFAULT NULL, ADD remise_motif VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP remise_montant, DROP remise_motif');
    }
}
