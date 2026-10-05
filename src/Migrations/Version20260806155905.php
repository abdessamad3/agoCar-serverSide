<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806155905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recurring_expense_template (id INT AUTO_INCREMENT NOT NULL, bureau_id INT NOT NULL, type_depense VARCHAR(50) NOT NULL, frequency VARCHAR(20) NOT NULL, price_type VARCHAR(20) NOT NULL, fixed_amount NUMERIC(10, 2) DEFAULT NULL, start_period_month INT DEFAULT NULL, start_period_year INT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_554405A32516FE2 (bureau_id), UNIQUE INDEX uniq_recurring_bureau_type (bureau_id, type_depense), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE recurring_expense_template ADD CONSTRAINT FK_554405A32516FE2 FOREIGN KEY (bureau_id) REFERENCES bureau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_request CHANGE date_debut date_debut DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE date_fin date_fin DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE cree_au cree_au DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE depense ADD recurring_template_id INT DEFAULT NULL, ADD is_auto_generated TINYINT(1) NOT NULL, ADD dismissed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD notification_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD period_month INT DEFAULT NULL, ADD period_year INT DEFAULT NULL');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757A4BD90CF FOREIGN KEY (recurring_template_id) REFERENCES recurring_expense_template (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_34059757A4BD90CF ON depense (recurring_template_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757A4BD90CF');
        $this->addSql('ALTER TABLE recurring_expense_template DROP FOREIGN KEY FK_554405A32516FE2');
        $this->addSql('DROP TABLE recurring_expense_template');
        $this->addSql('ALTER TABLE booking_request CHANGE date_debut date_debut DATE NOT NULL, CHANGE date_fin date_fin DATE NOT NULL, CHANGE cree_au cree_au DATETIME NOT NULL');
        $this->addSql('DROP INDEX IDX_34059757A4BD90CF ON depense');
        $this->addSql('ALTER TABLE depense DROP recurring_template_id, DROP is_auto_generated, DROP dismissed_at, DROP notification_sent_at, DROP period_month, DROP period_year');
    }
}
