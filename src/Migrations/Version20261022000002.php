<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Lets staff enter a manual flat surcharge for a second driver, folded into the
 *  reservation total the same way accessories and the discount already are. */
final class Version20261022000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prix to deuxieme_chauffeur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deuxieme_chauffeur ADD prix NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deuxieme_chauffeur DROP prix');
    }
}
