<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tarif_saisonnier table — seasonal/period pricing rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tarif_saisonnier (id INT AUTO_INCREMENT NOT NULL, bureau_id INT DEFAULT NULL, libelle VARCHAR(100) NOT NULL, date_debut DATE NOT NULL COMMENT '(DC2Type:date_immutable)', date_fin DATE NOT NULL COMMENT '(DC2Type:date_immutable)', type_ajustement VARCHAR(20) NOT NULL, valeur NUMERIC(10, 2) NOT NULL, actif TINYINT(1) DEFAULT 1 NOT NULL, cree_au DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_tarif_saisonnier_date_debut (date_debut), INDEX idx_tarif_saisonnier_date_fin (date_fin), INDEX idx_tarif_saisonnier_bureau (bureau_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE tarif_saisonnier ADD CONSTRAINT FK_TARIF_SAISONNIER_BUREAU FOREIGN KEY (bureau_id) REFERENCES bureau (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarif_saisonnier DROP FOREIGN KEY FK_TARIF_SAISONNIER_BUREAU');
        $this->addSql('DROP TABLE tarif_saisonnier');
    }
}
