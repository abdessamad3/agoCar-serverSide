<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make paiement.credit_id nullable; add paiement.reservation_id FK; add reservation.bureau_id FK (idempotent restore)';
    }

    public function up(Schema $schema): void
    {
        // superseded by Version20260614000001 which adds the same columns plus FK constraints
    }

    public function down(Schema $schema): void
    {
        // no-op: nothing was done in up()
    }
}
