<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create refresh_token table for JWT rotation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE refresh_token (
                id          INT AUTO_INCREMENT NOT NULL,
                utilisateur_id INT NOT NULL,
                token       VARCHAR(128) NOT NULL UNIQUE,
                expires_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                revoked     TINYINT(1) NOT NULL DEFAULT 0,
                INDEX IDX_F3F169BF3E5A5B9B (utilisateur_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE refresh_token
                ADD CONSTRAINT FK_refresh_token_utilisateur
                FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_refresh_token_utilisateur');
        $this->addSql('DROP TABLE refresh_token');
    }
}
