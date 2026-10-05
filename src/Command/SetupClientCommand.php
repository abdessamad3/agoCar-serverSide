<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:setup-client', description: 'Fresh client setup: wipe all data, create admin + bureau + parametres')]
class SetupClientCommand extends Command
{
    public function __construct(
        private Connection $db,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('AGOCAR Client Setup — Fresh Start');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'vehicle_credit_document', 'vehicle_credit_payment', 'vehicle_credit_installment',
            'vehicle_credit_reminder', 'vehicle_credit', 'achat_installment', 'achat_voiture',
            'vente', 'credit', 'email_log', 'activity_log', 'notification', 'infraction',
            'vehicle_return_inspection', 'vehicle_delivery', 'paiement', 'historique_paiement',
            'contrat', 'contract_extension', 'reservation', 'reservation_accessoire',
            'accessoire', 'damage', 'suivi_technique', 'reparation', 'vidange', 'vignette',
            'adblue', 'assurance', 'depense', 'paiement_depense', 'recurring_expense_template',
            'client_document', 'client', 'voiture_image', 'voiture', 'fournisseur',
            'financial_institution', 'booking_request', 'deuxieme_chauffeur',
            'conditions_contrat', 'parametres', 'refresh_token',
            'bureau', 'utilisateur', 'company',
        ] as $t) {
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

        // Parametres (client fills in their details via the UI)
        $this->db->executeStatement(
            "INSERT INTO parametres (company_name, city, currency, language) VALUES ('AGOCAR', '', 'MAD', 'fr')"
        );
        $io->writeln('+ parametres');

        // Conditions contrat (blank — client fills in via the UI)
        $fr = $this->db->quote('À compléter.');
        $ar = $this->db->quote('يرجى الإكمال.');
        $this->db->executeStatement(
            "INSERT INTO conditions_contrat (texte_francais, texte_arabe) VALUES ($fr, $ar)"
        );
        $io->writeln('+ conditions_contrat');

        // Admin user
        $user     = new Utilisateur();
        $hashed   = $this->hasher->hashPassword($user, 'Holla1997');
        $email    = $this->db->quote('abdessamadjibrane2@gmail.com');
        $roles    = $this->db->quote(json_encode(['ROLE_ADMIN']));
        $password = $this->db->quote($hashed);

        $this->db->executeStatement(
            "INSERT INTO utilisateur
                (email, roles, password, nom, prenom, actif, must_change_password, failed_password_attempts, cree_au)
             VALUES
                ($email, $roles, $password, 'Admin', 'AGOCAR', 1, 0, 0, '$now')"
        );
        $userId = (int) $this->db->lastInsertId();
        $io->writeln("+ utilisateur id=$userId (abdessamadjibrane2@gmail.com)");

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

        $io->success([
            'Setup terminé !',
            'Email    : abdessamadjibrane2@gmail.com',
            'Password : Holla1997',
            '',
            'Connectez-vous et complétez les Paramètres Société + Conditions Contrat.',
        ]);

        return Command::SUCCESS;
    }
}
