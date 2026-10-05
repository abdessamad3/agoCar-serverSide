<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move dateDebut/dateFin from expense-type tables into depense; rename depense.date → date_debut; add depense.date_fin';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        // 1. Rename depense.date → date_debut (skip if already renamed)
        if ($this->columnExists('depense', 'date')) {
            $this->addSql('ALTER TABLE depense CHANGE `date` date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }

        // 2. Add date_fin to depense
        if (!$this->columnExists('depense', 'date_fin')) {
            $this->addSql('ALTER TABLE depense ADD date_fin DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        }

        // 3. reparation: data migration only if both source column AND depense_id join exist
        if ($this->columnExists('reparation', 'date_debut') && $this->columnExists('reparation', 'depense_id')) {
            $this->addSql('UPDATE depense d INNER JOIN reparation r ON r.depense_id = d.id SET d.date_debut = COALESCE(r.date_debut, d.date_debut), d.date_fin = r.date_fin');
        }
        if ($this->columnExists('reparation', 'date_debut')) {
            $this->addSql('ALTER TABLE reparation DROP COLUMN date_debut');
        }
        if ($this->columnExists('reparation', 'date_fin')) {
            $this->addSql('ALTER TABLE reparation DROP COLUMN date_fin');
        }

        // 4. suivi_technique: data migration only if source columns AND depense_id join exist
        if ($this->columnExists('suivi_technique', 'date_reglages') && $this->columnExists('suivi_technique', 'date_fin') && $this->columnExists('suivi_technique', 'depense_id')) {
            $this->addSql('UPDATE depense d INNER JOIN suivi_technique s ON s.depense_id = d.id SET d.date_debut = COALESCE(s.date_reglages, d.date_debut), d.date_fin = s.date_fin');
        }
        if ($this->columnExists('suivi_technique', 'date_reglages')) {
            $this->addSql('ALTER TABLE suivi_technique DROP COLUMN date_reglages');
        }
        if ($this->columnExists('suivi_technique', 'date_fin')) {
            $this->addSql('ALTER TABLE suivi_technique DROP COLUMN date_fin');
        }

        // 5. vignette: data migration only if date_limite AND depense_id exist
        if ($this->columnExists('vignette', 'date_limite') && $this->columnExists('vignette', 'depense_id')) {
            $this->addSql('UPDATE depense d INNER JOIN vignette v ON v.depense_id = d.id SET d.date_fin = v.date_limite');
        }
        if ($this->columnExists('vignette', 'date_limite')) {
            $this->addSql('ALTER TABLE vignette DROP COLUMN date_limite');
        }

        // 6. assurance: data migration only if source columns AND depense_id exist
        if ($this->columnExists('assurance', 'date_debut') && $this->columnExists('assurance', 'depense_id')) {
            $this->addSql('UPDATE depense d INNER JOIN assurance a ON a.depense_id = d.id SET d.date_debut = COALESCE(a.date_debut, d.date_debut), d.date_fin = a.date_fin');
        }
        if ($this->columnExists('assurance', 'date_debut')) {
            $this->addSql('ALTER TABLE assurance DROP COLUMN date_debut');
        }
        if ($this->columnExists('assurance', 'date_fin')) {
            $this->addSql('ALTER TABLE assurance DROP COLUMN date_fin');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reparation ADD date_debut DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD date_fin DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE suivi_technique ADD date_reglages DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vignette ADD date_limite DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE assurance ADD date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE reparation r INNER JOIN depense d ON r.depense_id = d.id SET r.date_debut = DATE(d.date_debut), r.date_fin = d.date_fin');
        $this->addSql('UPDATE suivi_technique s INNER JOIN depense d ON s.depense_id = d.id SET s.date_reglages = d.date_debut, s.date_fin = COALESCE(d.date_fin, d.date_debut)');
        $this->addSql('UPDATE vignette v INNER JOIN depense d ON v.depense_id = d.id SET v.date_limite = COALESCE(d.date_fin, d.date_debut)');
        $this->addSql('UPDATE assurance a INNER JOIN depense d ON a.depense_id = d.id SET a.date_debut = d.date_debut, a.date_fin = COALESCE(d.date_fin, d.date_debut)');
        $this->addSql('ALTER TABLE depense CHANGE date_debut `date` DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE depense DROP COLUMN date_fin');
    }
}
