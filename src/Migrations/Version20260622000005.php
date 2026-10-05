<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce unique CIN, passport, and driving licence numbers per client — prevents the same identity document being registered against two different client records';
    }

    public function up(Schema $schema): void
    {
        // Empty strings must become NULL first — MySQL UNIQUE allows many NULLs but only one ''.
        $this->addSql("UPDATE client SET cin = NULL WHERE cin = ''");
        $this->addSql("UPDATE client SET passeport = NULL WHERE passeport = ''");
        $this->addSql("UPDATE client SET permis_conduite = NULL WHERE permis_conduite = ''");

        $this->addSql('ALTER TABLE client ADD CONSTRAINT UNIQ_C7440455B0286280 UNIQUE (cin)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT UNIQ_C7440455E0BC9856 UNIQUE (passeport)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT UNIQ_C7440455CA29F6A8 UNIQUE (permis_conduite)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP INDEX UNIQ_C7440455B0286280');
        $this->addSql('ALTER TABLE client DROP INDEX UNIQ_C7440455E0BC9856');
        $this->addSql('ALTER TABLE client DROP INDEX UNIQ_C7440455CA29F6A8');
    }
}
