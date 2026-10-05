<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add equipment_charge, remise_montant, remise_motif, caution_remboursee to vehicle_return_inspection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle_return_inspection
            ADD COLUMN equipment_charge  NUMERIC(10,2) DEFAULT NULL,
            ADD COLUMN remise_montant    NUMERIC(10,2) DEFAULT NULL,
            ADD COLUMN remise_motif      VARCHAR(255)  DEFAULT NULL,
            ADD COLUMN caution_remboursee NUMERIC(10,2) DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle_return_inspection
            DROP COLUMN IF EXISTS equipment_charge,
            DROP COLUMN IF EXISTS remise_montant,
            DROP COLUMN IF EXISTS remise_motif,
            DROP COLUMN IF EXISTS caution_remboursee
        ');
    }
}
