<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Phase 12.7: a soft-delete deleted_at column exists on these 7 tables in
 *  every real deployment but was never added by any migration. Additive
 *  only. Guarded, no-op where already present. */
final class Version20260523050015 extends AbstractMigration
{
    private const TABLES = ['accessoire', 'adblue', 'client', 'credit', 'infraction', 'paiement', 'reparation'];

    public function getDescription(): string
    {
        return 'Phase 12.7: add deleted_at to accessoire/adblue/client/credit/infraction/paiement/reparation (never captured by any migration)';
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchOne();
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $t) {
            if (!$this->columnExists($t, 'deleted_at')) {
                $this->addSql("ALTER TABLE `$t` ADD deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally a no-op -- see Version20260523050001.
    }
}
