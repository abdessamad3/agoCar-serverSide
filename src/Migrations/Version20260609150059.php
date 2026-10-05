<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609150059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vente table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vente (
            id INT AUTO_INCREMENT NOT NULL,
            voiture_id INT NOT NULL,
            bureau_id INT DEFAULT NULL,
            cree_par_id INT DEFAULT NULL,
            date_vente DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            prix_vente NUMERIC(12, 2) NOT NULL,
            acheteur VARCHAR(255) DEFAULT NULL,
            benefice NUMERIC(12, 2) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_vente_voiture (voiture_id),
            INDEX IDX_vente_bureau (bureau_id),
            INDEX IDX_vente_cree_par (cree_par_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_vente_voiture FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_vente_bureau FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE vente ADD CONSTRAINT FK_vente_cree_par FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_vente_voiture');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_vente_bureau');
        $this->addSql('ALTER TABLE vente DROP FOREIGN KEY FK_vente_cree_par');
        $this->addSql('DROP TABLE vente');
    }
}
