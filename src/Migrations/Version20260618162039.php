<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618162039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: add modePaiement/note to paiement, backfill from historique_paiement (reservation payments unification)';
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('paiement', 'mode_paiement')) {
            $this->addSql('ALTER TABLE paiement ADD mode_paiement VARCHAR(50) DEFAULT NULL, ADD note LONGTEXT DEFAULT NULL');
        }

        // Backfill from historique_paiement only if that table exists in this environment
        if ($this->tableExists('historique_paiement')) {
            $this->addSql(
                'INSERT INTO paiement (reservation_id, credit_id, cree_par_id, montant, date_paiement, statut, mode_paiement, note, cree_au, edit_au, deleted_at) '
                . "SELECT reservation_id, NULL, NULL, montant, date_paiement, 'payee', mode_paiement, note, cree_au, NULL, deleted_at FROM historique_paiement"
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('historique_paiement')) {
            $this->addSql(
                'DELETE p FROM paiement p '
                . 'INNER JOIN historique_paiement hp '
                . '  ON hp.reservation_id = p.reservation_id '
                . '  AND hp.montant = p.montant '
                . '  AND hp.date_paiement = p.date_paiement '
                . '  AND hp.cree_au = p.cree_au '
                . 'WHERE p.credit_id IS NULL'
            );
        }
        $this->addSql('ALTER TABLE paiement DROP mode_paiement, DROP note');
    }
}
