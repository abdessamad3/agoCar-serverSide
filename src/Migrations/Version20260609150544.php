<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609150544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create achat_installment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fournisseur (
            id INT AUTO_INCREMENT NOT NULL,
            raison_sociale VARCHAR(255) NOT NULL,
            nom VARCHAR(100) DEFAULT NULL,
            prenom VARCHAR(100) DEFAULT NULL,
            telephone VARCHAR(30) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            adresse VARCHAR(255) DEFAULT NULL,
            ville VARCHAR(100) DEFAULT NULL,
            ice VARCHAR(20) DEFAULT NULL,
            info_bancaire LONGTEXT DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE achat_voiture (
            id INT AUTO_INCREMENT NOT NULL,
            voiture_id INT DEFAULT NULL,
            fournisseur_id INT DEFAULT NULL,
            date_achat DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            prix_achat NUMERIC(12, 2) NOT NULL,
            apport NUMERIC(12, 2) DEFAULT NULL,
            type_financement VARCHAR(20) NOT NULL,
            mensualite NUMERIC(12, 2) DEFAULT NULL,
            taux_interet NUMERIC(5, 2) DEFAULT NULL,
            date_debut_credit DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\',
            reste_a_financer NUMERIC(12, 2) DEFAULT NULL,
            duree_mois INT DEFAULT NULL,
            dernier_mensualite NUMERIC(12, 2) DEFAULT NULL,
            statut VARCHAR(30) NOT NULL,
            notes LONGTEXT DEFAULT NULL,
            cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_achat_voiture_voiture (voiture_id),
            INDEX IDX_achat_voiture_fournisseur (fournisseur_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE achat_voiture ADD CONSTRAINT FK_achat_voiture_voiture FOREIGN KEY (voiture_id) REFERENCES voiture (id)');
        $this->addSql('ALTER TABLE achat_voiture ADD CONSTRAINT FK_achat_voiture_fournisseur FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id)');

        $this->addSql('CREATE TABLE achat_installment (
            id INT AUTO_INCREMENT NOT NULL,
            achat_voiture_id INT NOT NULL,
            installment_number INT NOT NULL,
            due_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            amount NUMERIC(12, 2) NOT NULL,
            amount_paid NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            paid_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\',
            notes LONGTEXT DEFAULT NULL,
            invoice_name VARCHAR(255) DEFAULT NULL,
            cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            edit_au DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_achat_installment_achat (achat_voiture_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE achat_installment ADD CONSTRAINT FK_achat_installment_achat FOREIGN KEY (achat_voiture_id) REFERENCES achat_voiture (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE achat_installment DROP FOREIGN KEY FK_achat_installment_achat');
        $this->addSql('DROP TABLE achat_installment');
        $this->addSql('ALTER TABLE achat_voiture DROP FOREIGN KEY FK_achat_voiture_voiture');
        $this->addSql('ALTER TABLE achat_voiture DROP FOREIGN KEY FK_achat_voiture_fournisseur');
        $this->addSql('DROP TABLE achat_voiture');
        $this->addSql('DROP TABLE fournisseur');
    }
}
