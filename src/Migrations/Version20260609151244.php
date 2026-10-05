<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609151244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vehicle_return_inspection table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vehicle_return_inspection (
            id INT AUTO_INCREMENT NOT NULL,
            reservation_id INT NOT NULL,
            inspected_by_id INT DEFAULT NULL,
            fuel_level SMALLINT DEFAULT NULL,
            kilometrage INT DEFAULT NULL,
            damages LONGTEXT DEFAULT NULL,
            photos JSON DEFAULT NULL COMMENT \'(DC2Type:json)\',
            notes LONGTEXT DEFAULT NULL,
            `condition` VARCHAR(30) DEFAULT \'clean\' NOT NULL,
            inspected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_vri_reservation (reservation_id),
            INDEX IDX_vri_inspected_by (inspected_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE vehicle_return_inspection ADD CONSTRAINT FK_vri_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_return_inspection ADD CONSTRAINT FK_vri_inspected_by FOREIGN KEY (inspected_by_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP FOREIGN KEY FK_vri_reservation');
        $this->addSql('ALTER TABLE vehicle_return_inspection DROP FOREIGN KEY FK_vri_inspected_by');
        $this->addSql('DROP TABLE vehicle_return_inspection');
    }
}
