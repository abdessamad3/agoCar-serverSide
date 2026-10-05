<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Documents columns that were added to the live database out-of-band (via
 * doctrine:schema:update) but were never captured by a migration: assurance
 * soft-delete/archive/company fields, vignette soft-delete/file fields,
 * suivi_technique soft-delete, depense soft-delete. Without this migration,
 * a fresh environment built from `doctrine:migrations:migrate` would not
 * match the current entity mapping or the current dev database.
 */
final class Version20260619000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill assurance/vignette/suivi_technique/depense columns that already exist live but were never migrated (deleted_at, archived_at, compagnie, type_assurance, vignette.file_path)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assurance ADD compagnie VARCHAR(100) DEFAULT NULL, ADD type_assurance VARCHAR(50) DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vignette ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD file_path VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE suivi_technique ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE depense ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assurance DROP compagnie, DROP type_assurance, DROP deleted_at, DROP archived_at');
        $this->addSql('ALTER TABLE vignette DROP deleted_at, DROP file_path');
        $this->addSql('ALTER TABLE suivi_technique DROP deleted_at');
        $this->addSql('ALTER TABLE depense DROP deleted_at');
    }
}
