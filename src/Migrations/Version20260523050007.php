<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: backfills paiement_depense, never captured by any migration.
 *  Created WITHOUT file_path -- Version20260829000002 adds that column
 *  later in the real chain via an unguarded ALTER; including it here too
 *  would make that later statement fail with a duplicate-column error. */
final class Version20260523050007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 12.7: create paiement_depense table (never captured by any migration)';
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
    }

    private function fkExists(string $table, string $fk): bool
    {
        return (bool) $this->connection->executeQuery(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND CONSTRAINT_NAME='$fk' LIMIT 1"
        )->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('paiement_depense')) {
            $this->addSql("CREATE TABLE paiement_depense (id INT AUTO_INCREMENT NOT NULL, depense_id INT DEFAULT NULL, cree_par_id INT DEFAULT NULL, edite_par_id INT DEFAULT NULL, supprime_par_id INT DEFAULT NULL, montant NUMERIC(10, 2) NOT NULL, date_paiement DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', note LONGTEXT DEFAULT NULL, payment_type VARCHAR(255) DEFAULT NULL, payment_method VARCHAR(255) DEFAULT NULL, payment_reference VARCHAR(30) DEFAULT NULL, cree_au DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', edit_au DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_293F27A641D81563 (depense_id), INDEX IDX_293F27A6FC29C013 (cree_par_id), INDEX IDX_293F27A611449526 (edite_par_id), INDEX IDX_293F27A6ACC02199 (supprime_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
        if (!$this->fkExists('paiement_depense', 'FK_293F27A641D81563')) {
            $this->addSql('ALTER TABLE paiement_depense ADD CONSTRAINT FK_293F27A641D81563 FOREIGN KEY (depense_id) REFERENCES depense (id)');
        }
        if (!$this->fkExists('paiement_depense', 'FK_293F27A6FC29C013')) {
            $this->addSql('ALTER TABLE paiement_depense ADD CONSTRAINT FK_293F27A6FC29C013 FOREIGN KEY (cree_par_id) REFERENCES utilisateur (id)');
        }
        if (!$this->fkExists('paiement_depense', 'FK_293F27A611449526')) {
            $this->addSql('ALTER TABLE paiement_depense ADD CONSTRAINT FK_293F27A611449526 FOREIGN KEY (edite_par_id) REFERENCES utilisateur (id)');
        }
        if (!$this->fkExists('paiement_depense', 'FK_293F27A6ACC02199')) {
            $this->addSql('ALTER TABLE paiement_depense ADD CONSTRAINT FK_293F27A6ACC02199 FOREIGN KEY (supprime_par_id) REFERENCES utilisateur (id)');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
