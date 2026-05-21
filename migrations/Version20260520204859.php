<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260520204859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accessoire CHANGE type_paiement type_paiement VARCHAR(50) DEFAULT NULL, CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE adblue CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE assurance CHANGE numero_contrat numero_contrat VARCHAR(50) DEFAULT NULL, CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE bureau CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client CHANGE cin cin VARCHAR(20) DEFAULT NULL, CHANGE passeport passeport VARCHAR(30) DEFAULT NULL, CHANGE permis_conduite permis_conduite VARCHAR(50) DEFAULT NULL, CHANGE nationalite nationalite VARCHAR(50) DEFAULT NULL, CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE contrat CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE credit CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE depense CHANGE date_paiement date_paiement DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE infraction CHANGE date_paiement date_paiement DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE paiement CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reparation CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reservation CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE suivi_technique CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE utilisateur CHANGE roles roles JSON NOT NULL, CHANGE cree_au cree_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vidange CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vignette CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE voiture CHANGE couleur couleur VARCHAR(30) DEFAULT NULL, CHANGE prix_achat prix_achat NUMERIC(10, 2) DEFAULT NULL, CHANGE edit_au edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE image_name image_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accessoire CHANGE type_paiement type_paiement VARCHAR(50) DEFAULT \'NULL\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE adblue CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE assurance CHANGE numero_contrat numero_contrat VARCHAR(50) DEFAULT \'NULL\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE bureau CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client CHANGE cin cin VARCHAR(20) DEFAULT \'NULL\', CHANGE passeport passeport VARCHAR(30) DEFAULT \'NULL\', CHANGE permis_conduite permis_conduite VARCHAR(50) DEFAULT \'NULL\', CHANGE nationalite nationalite VARCHAR(50) DEFAULT \'NULL\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE contrat CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE credit CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE depense CHANGE date_paiement date_paiement DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE infraction CHANGE date_paiement date_paiement DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE paiement CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reparation CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reservation CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE suivi_technique CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE utilisateur CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE cree_au cree_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vidange CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vignette CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE voiture CHANGE image_name image_name VARCHAR(255) DEFAULT \'NULL\', CHANGE couleur couleur VARCHAR(30) DEFAULT \'NULL\', CHANGE prix_achat prix_achat NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE edit_au edit_au DATETIME DEFAULT \'NULL\' COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
