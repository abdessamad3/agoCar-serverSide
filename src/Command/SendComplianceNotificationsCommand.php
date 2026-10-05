<?php

namespace App\Command;

use App\Repository\VoitureRepository;
use App\Service\ComplianceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-compliance-notifications',
    description: 'Send compliance warning notifications for vehicles with expiring or expired documents',
)]
class SendComplianceNotificationsCommand extends Command
{
    public function __construct(
        private VoitureRepository $voitureRepo,
        private ComplianceService $complianceService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Compliance Notifications');

        $voitures = $this->voitureRepo->findAll();
        $warned   = 0;
        $critical = 0;
        $blocked  = 0;

        foreach ($voitures as $voiture) {
            $status = $this->complianceService->getComplianceStatus($voiture);
            $label  = $voiture->getMarque() . ' ' . $voiture->getModele() . ' #' . $voiture->getId();

            foreach (['vignette', 'assurance', 'visite'] as $doc) {
                $info = $status[$doc];
                if ($info['status'] === ComplianceService::WARNING) {
                    $io->warning(sprintf('[WARNING] %s — %s expire dans %d jours (%s)', $label, $doc, $info['daysRemaining'], $info['expiresAt']));
                    $warned++;
                } elseif ($info['status'] === ComplianceService::CRITICAL) {
                    $io->caution(sprintf('[CRITICAL] %s — %s expire dans %d jours (%s)', $label, $doc, $info['daysRemaining'], $info['expiresAt']));
                    $critical++;
                } elseif ($info['status'] === ComplianceService::EXPIRED) {
                    $io->error(sprintf('[EXPIRED] %s — %s expiré le %s', $label, $doc, $info['expiresAt']));
                    $blocked++;
                }
            }
        }

        $io->success(sprintf(
            'Done. %d vehicles checked. Warnings: %d, Critical: %d, Expired/Blocked: %d',
            count($voitures), $warned, $critical, $blocked
        ));

        return Command::SUCCESS;
    }
}
