<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cin_expiration, passeport_expiration, permis_expiration to client — needed to warn staff at reservation time when a client document has expired';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD cin_expiration DATE DEFAULT NULL, ADD passeport_expiration DATE DEFAULT NULL, ADD permis_expiration DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP COLUMN cin_expiration, DROP COLUMN passeport_expiration, DROP COLUMN permis_expiration');
    }
}
