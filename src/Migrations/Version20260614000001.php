<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make paiement.credit_id nullable; add paiement.reservation_id FK; add reservation.bureau_id FK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement MODIFY credit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paiement ADD COLUMN reservation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_paiement_reservation FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_paiement_reservation ON paiement(reservation_id)');
        $this->addSql('ALTER TABLE reservation ADD COLUMN bureau_id INT DEFAULT NULL');
        $this->addSql('UPDATE reservation r INNER JOIN voiture v ON r.voiture_id = v.id SET r.bureau_id = v.bureau_id WHERE r.bureau_id IS NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_reservation_bureau FOREIGN KEY (bureau_id) REFERENCES bureau(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_reservation_bureau ON reservation(bureau_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_bureau ON reservation');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY IF EXISTS FK_reservation_bureau');
        $this->addSql('ALTER TABLE reservation DROP COLUMN IF EXISTS bureau_id');
        $this->addSql('DROP INDEX IF EXISTS idx_paiement_reservation ON paiement');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY IF EXISTS FK_paiement_reservation');
        $this->addSql('ALTER TABLE paiement DROP COLUMN IF EXISTS reservation_id');
        $this->addSql('ALTER TABLE paiement MODIFY credit_id INT NOT NULL');
    }
}
