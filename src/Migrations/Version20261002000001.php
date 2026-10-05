<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidate company legal identity onto parametres_societe (singleton); drop duplicated fields from parametres (per-bureau)';
    }

    public function up(Schema $schema): void
    {
        // ── 1) parametres_societe gets the fields it was missing ──────────────
        $this->addSql('ALTER TABLE parametres_societe ADD email VARCHAR(150) DEFAULT NULL, ADD website VARCHAR(255) DEFAULT NULL, ADD whatsapp VARCHAR(30) DEFAULT NULL');

        // ── 2) Copy over any real data already sitting in parametres, but only
        //      into parametres_societe fields that are still empty -- never
        //      overwrite data someone already entered on the Profil Societe page.
        //      parametres_societe is a strict singleton (id=1); if multiple
        //      bureaus have their own parametres row, prefer whichever one
        //      actually has a company_name filled in. ─────────────────────────
        $this->addSql("
            UPDATE parametres_societe ps, (
                SELECT * FROM parametres WHERE company_name IS NOT NULL AND company_name != '' ORDER BY id LIMIT 1
            ) p
            SET
                ps.raison_sociale = IF(ps.raison_sociale IS NULL OR ps.raison_sociale = '', p.company_name, ps.raison_sociale),
                ps.adresse        = IF(ps.adresse IS NULL OR ps.adresse = '', p.address, ps.adresse),
                ps.rc             = IF(ps.rc IS NULL OR ps.rc = '', p.rc, ps.rc),
                ps.ice            = IF(ps.ice IS NULL OR ps.ice = '', p.ice, ps.ice),
                ps.if_fiscal      = IF(ps.if_fiscal IS NULL OR ps.if_fiscal = '', p.tax_id, ps.if_fiscal),
                ps.cnss           = IF(ps.cnss IS NULL OR ps.cnss = '', p.cnss, ps.cnss),
                ps.logo_path      = IF(ps.logo_path IS NULL OR ps.logo_path = '', p.logo, ps.logo_path),
                ps.email          = IF(ps.email IS NULL OR ps.email = '', p.email, ps.email),
                ps.website        = IF(ps.website IS NULL OR ps.website = '', p.website, ps.website),
                ps.whatsapp       = IF(ps.whatsapp IS NULL OR ps.whatsapp = '', p.whatsapp, ps.whatsapp)
            WHERE ps.id = 1
        ");

        // ── 3) Drop the now-duplicated columns from parametres ─────────────────
        $this->addSql('ALTER TABLE parametres DROP company_name, DROP trade_name, DROP ice, DROP rc, DROP cnss, DROP tax_id, DROP address, DROP city, DROP region, DROP postal_code, DROP phone, DROP email, DROP website, DROP whatsapp, DROP logo');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametres ADD company_name VARCHAR(150) DEFAULT NULL, ADD trade_name VARCHAR(150) DEFAULT NULL, ADD ice VARCHAR(20) DEFAULT NULL, ADD rc VARCHAR(20) DEFAULT NULL, ADD cnss VARCHAR(20) DEFAULT NULL, ADD tax_id VARCHAR(20) DEFAULT NULL, ADD address VARCHAR(255) DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL, ADD region VARCHAR(100) DEFAULT NULL, ADD postal_code VARCHAR(10) DEFAULT NULL, ADD phone VARCHAR(30) DEFAULT NULL, ADD email VARCHAR(150) DEFAULT NULL, ADD website VARCHAR(255) DEFAULT NULL, ADD whatsapp VARCHAR(30) DEFAULT NULL, ADD logo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parametres_societe DROP email, DROP website, DROP whatsapp');
    }
}
