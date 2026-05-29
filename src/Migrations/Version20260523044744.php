<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260523044744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accessoire (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prix NUMERIC(10, 2) NOT NULL, description LONGTEXT DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE adblue (id INT AUTO_INCREMENT NOT NULL, depense_id INT NOT NULL, cree_par_id INT DEFAULT NULL, quantite_litre INT NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_A1AAB97941D81563 (depense_id), INDEX IDX_A1AAB979FC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE assurance (id INT AUTO_INCREMENT NOT NULL, depense_id INT NOT NULL, cree_par_id INT DEFAULT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', numero_contrat VARCHAR(50) DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_386829AE41D81563 (depense_id), INDEX IDX_386829AEFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE bureau (id INT AUTO_INCREMENT NOT NULL, cree_par_id INT DEFAULT NULL, nom VARCHAR(100) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, statut VARCHAR(50) NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_166FDEC4FC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, cree_par_id INT DEFAULT NULL, nom VARCHAR(100) NOT NULL, cin VARCHAR(20) DEFAULT NULL, passeport VARCHAR(30) DEFAULT NULL, permis_conduite VARCHAR(50) DEFAULT NULL, nationalite VARCHAR(50) DEFAULT NULL, telephone INT DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C7440455FC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contrat (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, reservation_id INT NOT NULL, cree_par_id INT DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6034999319EB6921 (client_id), UNIQUE INDEX UNIQ_60349993B83297E7 (reservation_id), INDEX IDX_60349993FC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE credit (id INT AUTO_INCREMENT NOT NULL, voiture_id INT DEFAULT NULL, cree_par_id INT DEFAULT NULL, montant_total NUMERIC(10, 2) NOT NULL, mensualite NUMERIC(10, 2) NOT NULL, date_debut DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', duree_mois INT NOT NULL, statut VARCHAR(50) NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_1CC16EFE181A8BA (voiture_id), INDEX IDX_1CC16EFEFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE depense (id INT AUTO_INCREMENT NOT NULL, voiture_id INT DEFAULT NULL, bureau_id INT DEFAULT NULL, cree_par_id INT DEFAULT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', type_depense VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, montant NUMERIC(10, 2) NOT NULL, statut VARCHAR(255) NOT NULL, date_paiement DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_34059757181A8BA (voiture_id), INDEX IDX_3405975732516FE2 (bureau_id), INDEX IDX_34059757FC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE infraction (id INT AUTO_INCREMENT NOT NULL, reservation_id INT DEFAULT NULL, cree_par_id INT DEFAULT NULL, numero_infraction INT NOT NULL, type VARCHAR(100) NOT NULL, date_saisie DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', prix NUMERIC(10, 2) NOT NULL, statut VARCHAR(50) NOT NULL, date_paiement DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C1A458F5B83297E7 (reservation_id), INDEX IDX_C1A458F5FC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, credit_id INT NOT NULL, cree_par_id INT DEFAULT NULL, montant NUMERIC(10, 2) NOT NULL, date_paiement DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', statut VARCHAR(50) NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B1DC7A1ECE062FF9 (credit_id), INDEX IDX_B1DC7A1EFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reparation (id INT AUTO_INCREMENT NOT NULL, depense_id INT NOT NULL, cree_par_id INT DEFAULT NULL, description_technique LONGTEXT NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_8FDF219D41D81563 (depense_id), INDEX IDX_8FDF219DFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, voiture_id INT NOT NULL, date_debut DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', date_fin DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', total NUMERIC(10, 2) NOT NULL, reservation_status VARCHAR(50) DEFAULT \'confirmed\' NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_42C8495519EB6921 (client_id), INDEX IDX_42C84955181A8BA (voiture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reservation_accessoire (reservation_id INT NOT NULL, accessoire_id INT NOT NULL, INDEX IDX_6857CBDEB83297E7 (reservation_id), INDEX IDX_6857CBDED23B67ED (accessoire_id), PRIMARY KEY(reservation_id, accessoire_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE suivi_technique (id INT AUTO_INCREMENT NOT NULL, voiture_id INT NOT NULL, cree_par_id INT DEFAULT NULL, date_reglages DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_fin DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BE9B632F181A8BA (voiture_id), INDEX IDX_BE9B632FFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, bureau_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1D1C63B332516FE2 (bureau_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vidange (id INT AUTO_INCREMENT NOT NULL, depense_id INT NOT NULL, cree_par_id INT DEFAULT NULL, kilometrage_suivant INT NOT NULL, filtre_air TINYINT(1) NOT NULL, filtre_huile TINYINT(1) NOT NULL, filtre_carburant TINYINT(1) NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_872AAB8B41D81563 (depense_id), INDEX IDX_872AAB8BFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vignette (id INT AUTO_INCREMENT NOT NULL, depense_id INT NOT NULL, cree_par_id INT DEFAULT NULL, annee INT NOT NULL, date_limite DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_B4B561E41D81563 (depense_id), INDEX IDX_B4B561EFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE voiture (id INT AUTO_INCREMENT NOT NULL, bureau_id INT NOT NULL, cree_par_id INT DEFAULT NULL, marque VARCHAR(60) NOT NULL, modele VARCHAR(60) NOT NULL, annee INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, kilometrage_actuel INT NOT NULL, type_carburant VARCHAR(30) NOT NULL, couleur VARCHAR(30) DEFAULT NULL, climatisation TINYINT(1) NOT NULL, prix_jour NUMERIC(10, 2) NOT NULL, prix_achat NUMERIC(10, 2) DEFAULT NULL, voiture_status VARCHAR(30) NOT NULL, reservation_status VARCHAR(30) NOT NULL, cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E9E2810F32516FE2 (bureau_id), INDEX IDX_E9E2810FFC29C013 (cree_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE adblue ADD CONSTRAINT FK_A1AAB97941D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE adblue ADD CONSTRAINT FK_A1AAB979FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE assurance ADD CONSTRAINT FK_386829AE41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE assurance ADD CONSTRAINT FK_386829AEFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE bureau ADD CONSTRAINT FK_166FDEC4FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_6034999319EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_60349993B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_60349993FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFE181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFEFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975732516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE infraction ADD CONSTRAINT FK_C1A458F5B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE infraction ADD CONSTRAINT FK_C1A458F5FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1ECE062FF9 FOREIGN KEY (credit_id) REFERENCES credit (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE reparation ADD CONSTRAINT FK_8FDF219D41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE reparation ADD CONSTRAINT FK_8FDF219DFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE reservation_accessoire ADD CONSTRAINT FK_6857CBDEB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_accessoire ADD CONSTRAINT FK_6857CBDED23B67ED FOREIGN KEY (accessoire_id) REFERENCES accessoire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE suivi_technique ADD CONSTRAINT FK_BE9B632F181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE suivi_technique ADD CONSTRAINT FK_BE9B632FFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B332516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE vidange ADD CONSTRAINT FK_872AAB8B41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE vidange ADD CONSTRAINT FK_872AAB8BFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE vignette ADD CONSTRAINT FK_B4B561E41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE vignette ADD CONSTRAINT FK_B4B561EFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810F32516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FFC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adblue DROP FOREIGN KEY FK_A1AAB97941D81563');
        $this->addSql('ALTER TABLE adblue DROP FOREIGN KEY FK_A1AAB979FC29C013');
        $this->addSql('ALTER TABLE assurance DROP FOREIGN KEY FK_386829AE41D81563');
        $this->addSql('ALTER TABLE assurance DROP FOREIGN KEY FK_386829AEFC29C013');
        $this->addSql('ALTER TABLE bureau DROP FOREIGN KEY FK_166FDEC4FC29C013');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455FC29C013');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_6034999319EB6921');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_60349993B83297E7');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_60349993FC29C013');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFE181A8BA');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFEFC29C013');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757181A8BA');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975732516FE2');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757FC29C013');
        $this->addSql('ALTER TABLE infraction DROP FOREIGN KEY FK_C1A458F5B83297E7');
        $this->addSql('ALTER TABLE infraction DROP FOREIGN KEY FK_C1A458F5FC29C013');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1ECE062FF9');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EFC29C013');
        $this->addSql('ALTER TABLE reparation DROP FOREIGN KEY FK_8FDF219D41D81563');
        $this->addSql('ALTER TABLE reparation DROP FOREIGN KEY FK_8FDF219DFC29C013');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495519EB6921');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955181A8BA');
        $this->addSql('ALTER TABLE reservation_accessoire DROP FOREIGN KEY FK_6857CBDEB83297E7');
        $this->addSql('ALTER TABLE reservation_accessoire DROP FOREIGN KEY FK_6857CBDED23B67ED');
        $this->addSql('ALTER TABLE suivi_technique DROP FOREIGN KEY FK_BE9B632F181A8BA');
        $this->addSql('ALTER TABLE suivi_technique DROP FOREIGN KEY FK_BE9B632FFC29C013');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B332516FE2');
        $this->addSql('ALTER TABLE vidange DROP FOREIGN KEY FK_872AAB8B41D81563');
        $this->addSql('ALTER TABLE vidange DROP FOREIGN KEY FK_872AAB8BFC29C013');
        $this->addSql('ALTER TABLE vignette DROP FOREIGN KEY FK_B4B561E41D81563');
        $this->addSql('ALTER TABLE vignette DROP FOREIGN KEY FK_B4B561EFC29C013');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810F32516FE2');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FFC29C013');
        $this->addSql('DROP TABLE accessoire');
        $this->addSql('DROP TABLE adblue');
        $this->addSql('DROP TABLE assurance');
        $this->addSql('DROP TABLE bureau');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE contrat');
        $this->addSql('DROP TABLE credit');
        $this->addSql('DROP TABLE depense');
        $this->addSql('DROP TABLE infraction');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE reparation');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE reservation_accessoire');
        $this->addSql('DROP TABLE suivi_technique');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE vidange');
        $this->addSql('DROP TABLE vignette');
        $this->addSql('DROP TABLE voiture');
    }
}
