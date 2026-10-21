<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds the public-site content fields to parametres_societe: ticker promo message,
 *  opening hours, social links, and the client/cities stats shown on the About page.
 *  All additive and nullable -- existing rows are unaffected, and the entity's
 *  getters fall back to the site's current hardcoded values when a column is null. */
final class Version20261021000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add promo_message, horaires, social links, and stat fields to parametres_societe for the public site';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametres_societe
            ADD promo_message JSON DEFAULT NULL,
            ADD horaires JSON DEFAULT NULL,
            ADD facebook_url VARCHAR(500) DEFAULT NULL,
            ADD instagram_url VARCHAR(500) DEFAULT NULL,
            ADD tiktok_url VARCHAR(500) DEFAULT NULL,
            ADD nombre_clients INT DEFAULT NULL,
            ADD villes_desservies INT DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametres_societe
            DROP promo_message,
            DROP horaires,
            DROP facebook_url,
            DROP instagram_url,
            DROP tiktok_url,
            DROP nombre_clients,
            DROP villes_desservies
        ');
    }
}
