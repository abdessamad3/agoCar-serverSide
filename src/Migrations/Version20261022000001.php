<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Lets an accessory be billed per rental day (existing behavior, default) or as a
 *  flat one-time fee regardless of rental length. */
final class Version20261022000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add type_tarification to accessoire (par_jour/unique)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE accessoire ADD type_tarification VARCHAR(20) NOT NULL DEFAULT 'par_jour'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accessoire DROP type_tarification');
    }
}
