<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hasTriangle to vehicle_delivery; add x/y to damage; drop legacy fuel_level+damages from vehicle_return_inspection; make contrat.numero nullable';
    }

    public function up(Schema $schema): void
    {
        // vehicle_delivery: add has_triangle
        $this->addSql('ALTER TABLE vehicle_delivery ADD has_triangle TINYINT(1) NOT NULL DEFAULT 0');

        // damage: add SVG position coordinates
        $this->addSql('ALTER TABLE damage ADD x DOUBLE PRECISION DEFAULT NULL, ADD y DOUBLE PRECISION DEFAULT NULL');

        // vehicle_return_inspection: drop legacy columns
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP COLUMN fuel_level');
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP COLUMN damages');

        // contrat: make numero nullable so the subscriber can set it on prePersist
        $this->addSql('ALTER TABLE contrat MODIFY numero VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle_delivery DROP COLUMN has_triangle');
        $this->addSql('ALTER TABLE damage DROP COLUMN x, DROP COLUMN y');
        $this->addSql('ALTER TABLE vehicle_return_inspection ADD fuel_level SMALLINT DEFAULT NULL, ADD damages LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE contrat MODIFY numero VARCHAR(30) NOT NULL');
    }
}
