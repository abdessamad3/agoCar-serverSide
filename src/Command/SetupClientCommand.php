<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Manual, operator-run bootstrap for a brand-new deployment. Never invoked
 * automatically -- not referenced by any deploy script, composer hook, or
 * other command. Still destructive (TRUNCATEs every business table), so it
 * requires an explicit admin email/password (no hardcoded fallback), refuses
 * to run in the 'prod' environment, and requires confirmation before wiping.
 */
#[AsCommand(name: 'app:setup-client', description: 'Fresh client setup: wipe all business data, create one admin + bureau + parametres')]
class SetupClientCommand extends Command
{
    // Phase 12.3: this list previously missed 5 tables added by later
    // migrations (company_staff, error_log, parametres_societe,
    // payment_attachment, tarif_saisonnier) -- a "fresh setup" wipe would
    // have silently left their rows behind. Cross-checked against every
    // table any migration creates, confirmed complete as of this phase.
    private const TABLES = [
        'vehicle_credit_document', 'vehicle_credit_payment', 'vehicle_credit_installment',
        'vehicle_credit_reminder', 'vehicle_credit', 'achat_installment', 'achat_voiture',
        'vente', 'credit', 'email_log', 'error_log', 'activity_log', 'notification', 'infraction',
        'vehicle_return_inspection', 'vehicle_delivery', 'paiement', 'payment_attachment', 'historique_paiement',
        'contrat', 'contract_extension', 'reservation', 'reservation_accessoire',
        'accessoire', 'damage', 'suivi_technique', 'reparation', 'vidange', 'vignette',
        'adblue', 'assurance', 'depense', 'paiement_depense', 'recurring_expense_template',
        'tarif_saisonnier', 'client_document', 'client', 'voiture_image', 'voiture', 'fournisseur',
        'financial_institution', 'booking_request', 'deuxieme_chauffeur',
        'conditions_contrat', 'parametres', 'parametres_societe', 'refresh_token',
        'company_staff', 'bureau', 'utilisateur', 'company',
    ];

    public function __construct(
        private Connection $db,
        private UserPasswordHasherInterface $hasher,
        private KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email for the new admin account')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Password for the new admin account (min 8 characters)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip the interactive wipe confirmation (admin email/password are still required)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('AGOCAR Client Setup — Fresh Start');

        // This permanently deletes every business record. There is no
        // legitimate reason to run it against a live production database
        // from a quick CLI invocation -- a real reset there, if ever needed,
        // should be a deliberate, separately-reviewed action, not something
        // this safeguard makes easy to trigger by accident.
        if ($this->kernel->getEnvironment() === 'prod') {
            $io->error([
                'Refusing to run app:setup-client in the "prod" environment.',
                'This command deletes every reservation, client, vehicle, contract, payment, and staff account.',
                'If a production reset is genuinely needed, do it deliberately and manually -- this guard is intentional.',
            ]);
            return Command::FAILURE;
        }

        $email = $this->resolveEmail($input, $io);
        if ($email === null) {
            return Command::FAILURE;
        }

        $password = $this->resolvePassword($input, $io);
        if ($password === null) {
            return Command::FAILURE;
        }

        $io->warning([
            'This will PERMANENTLY DELETE every business record in this database:',
            'reservations, clients, vehicles, contracts, payments, expenses, credits, documents, and all staff accounts.',
            "A single new admin account will be created for: $email",
        ]);

        if (!$input->getOption('force') && !$io->confirm('Wipe this database and continue?', false)) {
            $io->writeln('Aborted. No changes were made.');
            return Command::SUCCESS;
        }

        // Pre-flight: confirm every table this command expects actually
        // exists before truncating anything. Discovering a missing table
        // mid-loop would leave the database half-wiped with no way back.
        $schemaManager = $this->db->createSchemaManager();
        $missing = array_values(array_filter(
            self::TABLES,
            fn(string $t) => !$schemaManager->tablesExist([$t])
        ));
        if (!empty($missing)) {
            $io->error([
                'Aborting before making any changes -- this database is missing tables this command expects:',
                implode(', ', $missing),
                'Run pending migrations first (php bin/console doctrine:migrations:migrate), then retry.',
            ]);
            return Command::FAILURE;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABLES as $t) {
            $this->db->executeStatement("TRUNCATE TABLE `$t`");
            $io->writeln("  truncated: $t");
        }
        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        // Company
        $this->db->executeStatement(
            "INSERT INTO company (nom, cree_au) VALUES ('AGOCAR', '$now')"
        );
        $companyId = (int) $this->db->lastInsertId();
        $io->writeln("+ company id=$companyId");

        // Admin user (created before bureau: bureau.cree_par_id references it)
        $user        = new Utilisateur();
        $hashed      = $this->hasher->hashPassword($user, $password);
        $emailQuoted = $this->db->quote($email);
        $roles       = $this->db->quote(json_encode(['ROLE_ADMIN']));
        $passwordQ   = $this->db->quote($hashed);

        $this->db->executeStatement(
            "INSERT INTO utilisateur
                (email, roles, password, nom, prenom, actif, must_change_password, failed_password_attempts, cree_au)
             VALUES
                ($emailQuoted, $roles, $passwordQ, 'Admin', 'AGOCAR', 1, 0, 0, '$now')"
        );
        $userId = (int) $this->db->lastInsertId();
        $io->writeln("+ utilisateur id=$userId ($email)");

        // Bureau
        $this->db->executeStatement(
            "INSERT INTO bureau (nom, adresse, telephone, statut, company_id, cree_par_id, cree_au)
             VALUES ('Agence Principale', '', '', 'actif', $companyId, $userId, '$now')"
        );
        $bureauId = (int) $this->db->lastInsertId();
        $io->writeln("+ bureau id=$bureauId");

        // Link user → bureau
        $this->db->executeStatement(
            "UPDATE utilisateur SET bureau_id=$bureauId WHERE id=$userId"
        );
        $io->writeln('+ user linked to bureau');

        // Parametres (operational settings) -- per-bureau, bureau_id is NOT
        // NULL with no default. Everything else is left at its column
        // default; the client fills in the rest via the UI.
        $this->db->executeStatement(
            "INSERT INTO parametres (bureau_id, currency, language) VALUES ($bureauId, 'MAD', 'fr')"
        );
        $io->writeln('+ parametres');

        // Parametres Societe (company identity/branding) -- a singleton row,
        // id=1, separate from the per-bureau operational settings above.
        $this->db->executeStatement(
            "INSERT INTO parametres_societe (id, raison_sociale, telephones) VALUES (1, 'AGOCAR', '[]')"
        );
        $io->writeln('+ parametres_societe');

        // Conditions contrat (blank — client fills in via the UI)
        $fr = $this->db->quote('À compléter.');
        $ar = $this->db->quote('يرجى الإكمال.');
        $this->db->executeStatement(
            "INSERT INTO conditions_contrat (texte_francais, texte_arabe) VALUES ($fr, $ar)"
        );
        $io->writeln('+ conditions_contrat');

        // The password is never echoed back -- the operator just typed or
        // passed it, so printing it again only serves a terminal scrollback
        // or CI log that may outlive the one moment it's needed.
        $io->success([
            'Setup terminé !',
            "Email : $email",
            '',
            'Connectez-vous et complétez les Paramètres Société + Conditions Contrat.',
        ]);

        return Command::SUCCESS;
    }

    private function resolveEmail(InputInterface $input, SymfonyStyle $io): ?string
    {
        $email = $input->getOption('admin-email');

        if ($email === null) {
            if (!$input->isInteractive()) {
                $io->error('--admin-email is required when running non-interactively.');
                return null;
            }
            $email = $io->ask('Admin email for the new account', null, function (?string $v) {
                if (!$v || !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('A valid email is required.');
                }
                return $v;
            });
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('--admin-email is not a valid email address.');
            return null;
        }

        return $email;
    }

    private function resolvePassword(InputInterface $input, SymfonyStyle $io): ?string
    {
        $password = $input->getOption('admin-password');

        if ($password === null) {
            if (!$input->isInteractive()) {
                $io->error('--admin-password is required when running non-interactively.');
                return null;
            }
            $password = $io->askHidden('Admin password for the new account (min 8 characters)', function (?string $v) {
                if (!$v || strlen($v) < 8) {
                    throw new \RuntimeException('Password must be at least 8 characters.');
                }
                return $v;
            });
        } elseif (strlen($password) < 8) {
            $io->error('--admin-password must be at least 8 characters.');
            return null;
        }

        return $password;
    }
}
