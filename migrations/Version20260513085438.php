<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513085438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accessoire (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prix NUMERIC(10, 2) NOT NULL, type_paiment VARCHAR(50) DEFAULT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, cree_par_id INT NOT NULL, INDEX IDX_8FD026AFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE accessoire_reservation (accessoire_id INT NOT NULL, reservation_id INT NOT NULL, INDEX IDX_D8166332D23B67ED (accessoire_id), INDEX IDX_D8166332B83297E7 (reservation_id), PRIMARY KEY (accessoire_id, reservation_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE adblue (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, prix NUMERIC(10, 2) NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, depense_id INT NOT NULL, cree_par_id INT NOT NULL, UNIQUE INDEX UNIQ_A1AAB97941D81563 (depense_id), INDEX IDX_A1AAB979FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE assurance (id INT AUTO_INCREMENT NOT NULL, prix NUMERIC(10, 2) NOT NULL, date_paiment DATE NOT NULL, statut VARCHAR(30) NOT NULL, numero_moi INT NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, depense_id INT NOT NULL, UNIQUE INDEX UNIQ_386829AE41D81563 (depense_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE assurance_utilisateur (assurance_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_786E2157B288C3E3 (assurance_id), INDEX IDX_786E2157FB88E14F (utilisateur_id), PRIMARY KEY (assurance_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bureau (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, status VARCHAR(50) NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, cree_par_id INT NOT NULL, INDEX IDX_166FDEC4FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, cin VARCHAR(20) DEFAULT NULL, passeport VARCHAR(30) DEFAULT NULL, permis_conduite VARCHAR(50) DEFAULT NULL, nationalite VARCHAR(50) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, cree_par_id INT NOT NULL, INDEX IDX_C7440455FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contrat (id INT AUTO_INCREMENT NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, client_id INT NOT NULL, cree_par_id INT NOT NULL, reservation_id INT NOT NULL, INDEX IDX_6034999319EB6921 (client_id), INDEX IDX_60349993FC29C013 (cree_par_id), UNIQUE INDEX UNIQ_60349993B83297E7 (reservation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE credit (id INT AUTO_INCREMENT NOT NULL, montant_total NUMERIC(10, 2) NOT NULL, mensualite NUMERIC(10, 2) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, duree_mois INT NOT NULL, statut VARCHAR(30) NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, voiture_id INT NOT NULL, cree_par_id INT NOT NULL, UNIQUE INDEX UNIQ_1CC16EFE181A8BA (voiture_id), INDEX IDX_1CC16EFEFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE depense (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, type_depense VARCHAR(30) NOT NULL, description LONGTEXT DEFAULT NULL, montant NUMERIC(10, 2) NOT NULL, status VARCHAR(30) NOT NULL, date_paiment DATE DEFAULT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, voiture_id INT NOT NULL, bureau_id INT NOT NULL, cree_par_id INT NOT NULL, vidange_id INT NOT NULL, INDEX IDX_34059757181A8BA (voiture_id), INDEX IDX_3405975732516FE2 (bureau_id), INDEX IDX_34059757FC29C013 (cree_par_id), INDEX IDX_340597571D699D22 (vidange_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE infraction (id INT AUTO_INCREMENT NOT NULL, numero_infraction INT NOT NULL, type VARCHAR(50) NOT NULL, date_saisie DATE NOT NULL, prix NUMERIC(10, 2) NOT NULL, status VARCHAR(30) NOT NULL, date_paiment DATE DEFAULT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, reservation_id INT NOT NULL, cree_par_id INT NOT NULL, INDEX IDX_C1A458F5B83297E7 (reservation_id), INDEX IDX_C1A458F5FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, date_paiement DATE NOT NULL, statut VARCHAR(30) NOT NULL, cree_au DATETIME NOT NULL, credit_id INT NOT NULL, cree_par_id INT NOT NULL, INDEX IDX_B1DC7A1ECE062FF9 (credit_id), INDEX IDX_B1DC7A1EFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reparation (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, description LONGTEXT NOT NULL, prix_total NUMERIC(10, 2) NOT NULL, prix_payee NUMERIC(10, 2) NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, depense_id INT NOT NULL, cree_par_id INT NOT NULL, UNIQUE INDEX UNIQ_8FDF219D41D81563 (depense_id), INDEX IDX_8FDF219DFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, total NUMERIC(10, 2) NOT NULL, montant_paye NUMERIC(10, 2) NOT NULL, montant_restant NUMERIC(10, 2) NOT NULL, lavage TINYINT NOT NULL, description LONGTEXT DEFAULT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, client_id INT NOT NULL, voiture_id INT NOT NULL, cree_par_id INT NOT NULL, INDEX IDX_42C8495519EB6921 (client_id), INDEX IDX_42C84955181A8BA (voiture_id), INDEX IDX_42C84955FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE suivi_technique (id INT AUTO_INCREMENT NOT NULL, date_reglages DATE NOT NULL, date_fin DATE NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, voiture_id INT NOT NULL, cree_par_id INT NOT NULL, INDEX IDX_BE9B632F181A8BA (voiture_id), INDEX IDX_BE9B632FFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `utilisateur` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, cree_au VARCHAR(255) DEFAULT NULL, edit_au VARCHAR(255) DEFAULT NULL, bureau_id INT NOT NULL, cree_par_id INT NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), INDEX IDX_1D1C63B332516FE2 (bureau_id), INDEX IDX_1D1C63B3FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vidange (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, kilometrage_suivant INT NOT NULL, filtre_air TINYINT NOT NULL, filtre_huile TINYINT NOT NULL, filtre_carburant TINYINT NOT NULL, prix_total NUMERIC(10, 2) NOT NULL, prix_payee NUMERIC(10, 2) NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, cree_par_id INT NOT NULL, INDEX IDX_872AAB8BFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vignette (id INT AUTO_INCREMENT NOT NULL, annee INT NOT NULL, prix NUMERIC(10, 2) NOT NULL, date_paiment DATE NOT NULL, date_limite DATE NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, depense_id INT NOT NULL, cree_par_id INT NOT NULL, UNIQUE INDEX UNIQ_B4B561E41D81563 (depense_id), INDEX IDX_B4B561EFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voiture (id INT AUTO_INCREMENT NOT NULL, marque VARCHAR(60) NOT NULL, modele VARCHAR(60) NOT NULL, annee INT NOT NULL, kilometrage_actuel INT NOT NULL, type_carburant VARCHAR(30) NOT NULL, couleur VARCHAR(30) DEFAULT NULL, climatisation TINYINT NOT NULL, prix_jour NUMERIC(10, 2) NOT NULL, prix_achat NUMERIC(10, 2) DEFAULT NULL, voiture_status VARCHAR(30) NOT NULL, reservation_status VARCHAR(30) NOT NULL, cree_au DATETIME NOT NULL, edit_au DATETIME DEFAULT NULL, bureau_id INT NOT NULL, client_id INT NOT NULL, cree_par_id INT NOT NULL, INDEX IDX_E9E2810F32516FE2 (bureau_id), INDEX IDX_E9E2810F19EB6921 (client_id), INDEX IDX_E9E2810FFC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE accessoire ADD CONSTRAINT FK_8FD026AFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE accessoire_reservation ADD CONSTRAINT FK_D8166332D23B67ED FOREIGN KEY (accessoire_id) REFERENCES accessoire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accessoire_reservation ADD CONSTRAINT FK_D8166332B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE adblue ADD CONSTRAINT FK_A1AAB97941D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE adblue ADD CONSTRAINT FK_A1AAB979FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE assurance ADD CONSTRAINT FK_386829AE41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE assurance_utilisateur ADD CONSTRAINT FK_786E2157B288C3E3 FOREIGN KEY (assurance_id) REFERENCES assurance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assurance_utilisateur ADD CONSTRAINT FK_786E2157FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `utilisateur` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bureau ADD CONSTRAINT FK_166FDEC4FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_6034999319EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_60349993FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE contrat ADD CONSTRAINT FK_60349993B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFE181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFEFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975732516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_340597571D699D22 FOREIGN KEY (vidange_id) REFERENCES vidange (id)');
        $this->addSql('ALTER TABLE infraction ADD CONSTRAINT FK_C1A458F5B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE infraction ADD CONSTRAINT FK_C1A458F5FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1ECE062FF9 FOREIGN KEY (credit_id) REFERENCES credit (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE reparation ADD CONSTRAINT FK_8FDF219D41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE reparation ADD CONSTRAINT FK_8FDF219DFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE suivi_technique ADD CONSTRAINT FK_BE9B632F181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE suivi_technique ADD CONSTRAINT FK_BE9B632FFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE `utilisateur` ADD CONSTRAINT FK_1D1C63B332516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE `utilisateur` ADD CONSTRAINT FK_1D1C63B3FC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE vidange ADD CONSTRAINT FK_872AAB8BFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE vignette ADD CONSTRAINT FK_B4B561E41D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        $this->addSql('ALTER TABLE vignette ADD CONSTRAINT FK_B4B561EFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810F32516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810F19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FFC29C013 FOREIGN KEY (cree_par_id) REFERENCES `utilisateur` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accessoire DROP FOREIGN KEY FK_8FD026AFC29C013');
        $this->addSql('ALTER TABLE accessoire_reservation DROP FOREIGN KEY FK_D8166332D23B67ED');
        $this->addSql('ALTER TABLE accessoire_reservation DROP FOREIGN KEY FK_D8166332B83297E7');
        $this->addSql('ALTER TABLE adblue DROP FOREIGN KEY FK_A1AAB97941D81563');
        $this->addSql('ALTER TABLE adblue DROP FOREIGN KEY FK_A1AAB979FC29C013');
        $this->addSql('ALTER TABLE assurance DROP FOREIGN KEY FK_386829AE41D81563');
        $this->addSql('ALTER TABLE assurance_utilisateur DROP FOREIGN KEY FK_786E2157B288C3E3');
        $this->addSql('ALTER TABLE assurance_utilisateur DROP FOREIGN KEY FK_786E2157FB88E14F');
        $this->addSql('ALTER TABLE bureau DROP FOREIGN KEY FK_166FDEC4FC29C013');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455FC29C013');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_6034999319EB6921');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_60349993FC29C013');
        $this->addSql('ALTER TABLE contrat DROP FOREIGN KEY FK_60349993B83297E7');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFE181A8BA');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFEFC29C013');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757181A8BA');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975732516FE2');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757FC29C013');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_340597571D699D22');
        $this->addSql('ALTER TABLE infraction DROP FOREIGN KEY FK_C1A458F5B83297E7');
        $this->addSql('ALTER TABLE infraction DROP FOREIGN KEY FK_C1A458F5FC29C013');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1ECE062FF9');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EFC29C013');
        $this->addSql('ALTER TABLE reparation DROP FOREIGN KEY FK_8FDF219D41D81563');
        $this->addSql('ALTER TABLE reparation DROP FOREIGN KEY FK_8FDF219DFC29C013');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495519EB6921');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955181A8BA');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955FC29C013');
        $this->addSql('ALTER TABLE suivi_technique DROP FOREIGN KEY FK_BE9B632F181A8BA');
        $this->addSql('ALTER TABLE suivi_technique DROP FOREIGN KEY FK_BE9B632FFC29C013');
        $this->addSql('ALTER TABLE `utilisateur` DROP FOREIGN KEY FK_1D1C63B332516FE2');
        $this->addSql('ALTER TABLE `utilisateur` DROP FOREIGN KEY FK_1D1C63B3FC29C013');
        $this->addSql('ALTER TABLE vidange DROP FOREIGN KEY FK_872AAB8BFC29C013');
        $this->addSql('ALTER TABLE vignette DROP FOREIGN KEY FK_B4B561E41D81563');
        $this->addSql('ALTER TABLE vignette DROP FOREIGN KEY FK_B4B561EFC29C013');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810F32516FE2');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810F19EB6921');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FFC29C013');
        $this->addSql('DROP TABLE accessoire');
        $this->addSql('DROP TABLE accessoire_reservation');
        $this->addSql('DROP TABLE adblue');
        $this->addSql('DROP TABLE assurance');
        $this->addSql('DROP TABLE assurance_utilisateur');
        $this->addSql('DROP TABLE bureau');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE contrat');
        $this->addSql('DROP TABLE credit');
        $this->addSql('DROP TABLE depense');
        $this->addSql('DROP TABLE infraction');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE reparation');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE suivi_technique');
        $this->addSql('DROP TABLE `utilisateur`');
        $this->addSql('DROP TABLE vidange');
        $this->addSql('DROP TABLE vignette');
        $this->addSql('DROP TABLE voiture');
    }
}
