<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260825225845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE damage ADD voiture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE damage ADD CONSTRAINT FK_11C8546C181A8BA FOREIGN KEY (voiture_id) REFERENCES voiture (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_11C8546C181A8BA ON damage (voiture_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE damage DROP FOREIGN KEY FK_11C8546C181A8BA');
        $this->addSql('DROP INDEX IDX_11C8546C181A8BA ON damage');
        $this->addSql('ALTER TABLE damage DROP voiture_id');
    }
}
