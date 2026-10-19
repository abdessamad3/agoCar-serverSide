<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Re-scopes tarif_saisonnier from bureau (branch) to voiture (individual vehicle):
 *  seasonal pricing rules vary by vehicle demand, not by branch location, and this
 *  table had no real usage yet (no rows depend on the old scoping) so this is a
 *  straight column swap rather than a data migration. */
final class Version20261019000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Re-scope tarif_saisonnier from bureau_id to voiture_id (seasonal rates apply per-vehicle, not per-branch)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarif_saisonnier DROP FOREIGN KEY FK_TARIF_SAISONNIER_BUREAU');
        $this->addSql('DROP INDEX idx_tarif_saisonnier_bureau ON tarif_saisonnier');
        $this->addSql('ALTER TABLE tarif_saisonnier CHANGE bureau_id voiture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tarif_saisonnier ADD CONSTRAINT FK_TARIF_SAISONNIER_VOITURE FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_tarif_saisonnier_voiture ON tarif_saisonnier (voiture_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarif_saisonnier DROP FOREIGN KEY FK_TARIF_SAISONNIER_VOITURE');
        $this->addSql('DROP INDEX idx_tarif_saisonnier_voiture ON tarif_saisonnier');
        $this->addSql('ALTER TABLE tarif_saisonnier CHANGE voiture_id bureau_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tarif_saisonnier ADD CONSTRAINT FK_TARIF_SAISONNIER_BUREAU FOREIGN KEY (bureau_id) REFERENCES bureau (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_tarif_saisonnier_bureau ON tarif_saisonnier (bureau_id)');
    }
}
