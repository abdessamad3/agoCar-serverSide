<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replaces the indexes lost when vignette.date_limite/suivi_technique.date_fin
 * were centralized onto depense.date_fin (Version20260616000002) — no
 * replacement index was ever added, despite depense.date_fin/date_debut/
 * type_depense being the most-filtered columns in ComplianceService,
 * ProfitabilityService, and the dashboard aggregation queries.
 */
final class Version20260620000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on depense.date_fin, date_debut, type_depense, and a composite (voiture_id, type_depense, date_fin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_depense_date_fin ON depense (date_fin)');
        $this->addSql('CREATE INDEX idx_depense_date_debut ON depense (date_debut)');
        $this->addSql('CREATE INDEX idx_depense_type_depense ON depense (type_depense)');
        $this->addSql('CREATE INDEX idx_depense_voiture_type_datefin ON depense (voiture_id, type_depense, date_fin)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_depense_date_fin ON depense');
        $this->addSql('DROP INDEX idx_depense_date_debut ON depense');
        $this->addSql('DROP INDEX idx_depense_type_depense ON depense');
        $this->addSql('DROP INDEX idx_depense_voiture_type_datefin ON depense');
    }
}
