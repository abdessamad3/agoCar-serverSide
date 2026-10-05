<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hasTriangle to vehicle_delivery; add x/y to damage; drop legacy fuel_level+damages from vehicle_return_inspection; make contrat.numero nullable';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('vehicle_delivery', 'has_triangle')) {
            $this->addSql('ALTER TABLE vehicle_delivery ADD COLUMN has_triangle TINYINT(1) NOT NULL DEFAULT 0');
        }

        if (!$this->columnExists('damage', 'x')) {
            $this->addSql('ALTER TABLE damage ADD COLUMN x DOUBLE PRECISION DEFAULT NULL');
            $this->addSql('ALTER TABLE damage ADD COLUMN y DOUBLE PRECISION DEFAULT NULL');
        }

        if ($this->columnExists('vehicle_return_inspection', 'fuel_level')) {
            $this->addSql('ALTER TABLE vehicle_return_inspection DROP COLUMN fuel_level');
        }
        if ($this->columnExists('vehicle_return_inspection', 'damages')) {
            $this->addSql('ALTER TABLE vehicle_return_inspection DROP COLUMN damages');
        }

        // MODIFY is idempotent — safe to run multiple times
        $this->addSql('ALTER TABLE contrat MODIFY COLUMN numero VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if ($this->columnExists('vehicle_delivery', 'has_triangle')) {
            $this->addSql('ALTER TABLE vehicle_delivery DROP COLUMN has_triangle');
        }
        if ($this->columnExists('damage', 'x')) {
            $this->addSql('ALTER TABLE damage DROP COLUMN x');
        }
        if ($this->columnExists('damage', 'y')) {
            $this->addSql('ALTER TABLE damage DROP COLUMN y');
        }
        if (!$this->columnExists('vehicle_return_inspection', 'fuel_level')) {
            $this->addSql('ALTER TABLE vehicle_return_inspection ADD COLUMN fuel_level SMALLINT DEFAULT NULL');
        }
        if (!$this->columnExists('vehicle_return_inspection', 'damages')) {
            $this->addSql('ALTER TABLE vehicle_return_inspection ADD COLUMN damages LONGTEXT DEFAULT NULL');
        }
        $this->addSql('ALTER TABLE contrat MODIFY COLUMN numero VARCHAR(30) NOT NULL');
    }
}
