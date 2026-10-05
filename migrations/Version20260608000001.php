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
        // Reservation — common filters: by vehicle, by client, by date range
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reservation_voiture    ON reservation (voiture_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reservation_client     ON reservation (client_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reservation_date_debut ON reservation (date_debut)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reservation_date_fin   ON reservation (date_fin)');

        // Depense — most queries filter by vehicle
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_depense_voiture ON depense (voiture_id)');

        // Notification — queries always filter by user and/or read status
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notification_user    ON notification (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notification_read_at ON notification (read_at)');

        // Compliance expiry lookups — daily cron scans these for upcoming/expired records
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_assurance_date_fin         ON assurance (date_fin)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vignette_date_limite        ON vignette (date_limite)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_suivi_technique_date_fin    ON suivi_technique (date_fin)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_voiture    ON reservation');
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_client     ON reservation');
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_date_debut ON reservation');
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_date_fin   ON reservation');
        $this->addSql('DROP INDEX IF EXISTS idx_depense_voiture        ON depense');
        $this->addSql('DROP INDEX IF EXISTS idx_notification_user      ON notification');
        $this->addSql('DROP INDEX IF EXISTS idx_notification_read_at   ON notification');
        $this->addSql('DROP INDEX IF EXISTS idx_assurance_date_fin     ON assurance');
        $this->addSql('DROP INDEX IF EXISTS idx_vignette_date_limite   ON vignette');
        $this->addSql('DROP INDEX IF EXISTS idx_suivi_technique_date_fin ON suivi_technique');
    }
}
