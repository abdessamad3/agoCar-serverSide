<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.9 Stage C2-C: explicit, nullable Reservation -> Utilisateur ownership
 *  link for the customer portal. Additive only -- no existing column, index, or
 *  constraint is touched, and no historical row is backfilled (ownership is only
 *  ever set going forward, by the application, never by this migration). */
final class Version20261018000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.9 Stage C2-C: add nullable reservation.utilisateur_id (ON DELETE SET NULL) for explicit customer ownership';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_RESERVATION_UTILISATEUR FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_reservation_utilisateur ON reservation (utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_RESERVATION_UTILISATEUR');
        $this->addSql('DROP INDEX idx_reservation_utilisateur ON reservation');
        $this->addSql('ALTER TABLE reservation DROP utilisateur_id');
    }
}
