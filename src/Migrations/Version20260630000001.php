<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create booking_request table for the public car rental website';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking_request (
            id INT AUTO_INCREMENT NOT NULL,
            voiture_id INT NOT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(180) NOT NULL,
            lieu_livraison VARCHAR(255) DEFAULT NULL,
            message LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'new\',
            cree_au DATETIME NOT NULL,
            INDEX idx_booking_request_voiture (voiture_id),
            INDEX idx_booking_request_status (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE booking_request ADD CONSTRAINT FK_booking_request_voiture FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking_request DROP FOREIGN KEY FK_booking_request_voiture');
        $this->addSql('DROP TABLE booking_request');
    }
}
