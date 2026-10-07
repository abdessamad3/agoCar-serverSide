<?php

namespace App\Command;

use App\Entity\Reservation;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Fleet\Exception\InvalidReservationTransitionException;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\ReservationLifecycleManager;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Auto-advances reservation statuses based on date boundaries.
 *
 * confirmed  + dateDebut <= now <= dateFin    → en_cours
 * en_cours   + dateFin  <  now                → terminee
 *
 * Routed through ReservationLifecycleManager (validated transition + guard +
 * activity log) and FleetLifecycleManager (re-syncs the vehicle's lifecycle
 * state) exactly like the manual endpoints — this command previously wrote
 * reservationStatus directly, matched the legacy 'confirmee' spelling that
 * no live write path produces anymore (so that half silently matched zero
 * rows), and never touched Voiture.voitureStatus at all.
 *
 * Run every few minutes via cron:
 *   * * * * * php /path/to/project/bin/console reservation:sync-status >> /var/log/reservation_sync.log 2>&1
 */
#[AsCommand(
    name:        'reservation:sync-status',
    description: 'Auto-transitions confirmed→en_cours and en_cours→terminee based on dates.',
)]
class SyncReservationStatusCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private ReservationLifecycleManager $reservationLifecycle,
        private FleetLifecycleManager $flm,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $started  = $this->transition(
            fromStatuses: ['confirmed', 'confirmee'],
            toStatus:     'en_cours',
            condition:    'r.dateDebut <= :now AND r.dateFin >= :now',
            now:          $now,
            io:           $io,
        );

        $finished = $this->transition(
            fromStatuses: ['en_cours'],
            toStatus:     'terminee',
            condition:    'r.dateFin < :now',
            now:          $now,
            io:           $io,
        );

        $io->success(sprintf(
            '%d confirmed→en_cours, %d en_cours→terminee.',
            $started, $finished
        ));

        return Command::SUCCESS;
    }

    /** @param string[] $fromStatuses */
    private function transition(
        array $fromStatuses,
        string $toStatus,
        string $condition,
        \DateTimeImmutable $now,
        SymfonyStyle $io,
    ): int {
        $reservations = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.reservationStatus IN (:from)')
            ->andWhere($condition)
            ->setParameter('from', $fromStatuses)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($reservations as $r) {
            try {
                $this->reservationLifecycle->transition($r, $toStatus, ['action' => 'auto_sync_date_based']);
            } catch (InvalidReservationTransitionException $e) {
                $io->warning(sprintf('[reservation:sync-status] Skipped reservation #%d: %s', $r->getId(), $e->getMessage()));
                continue;
            }
            $count++;

            $voiture = $r->getVoiture();
            if ($voiture !== null) {
                $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));
            }

            if ($toStatus === 'terminee') {
                $restant = $r->getMontantRestant();
                if ($restant > 0) {
                    $client   = $r->getClient();
                    $carLabel = $voiture
                        ? $voiture->getMarque() . ' ' . $voiture->getModele()
                            . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '')
                        : 'véhicule';
                    $this->notificationService->createForAllUsers(
                        NotificationService::TYPE_RESERVATION_UNPAID,
                        'reservation',
                        $r->getId(),
                        "Contrat clôturé avec solde impayé - $carLabel",
                        trim($client?->getNom() ?? 'Client inconnu') . ' doit encore ' . number_format($restant, 0, ',', ' ') . ' MAD.',
                        NotificationService::PRIORITY_HIGH,
                        '/client-debts',
                    );
                }
            }
        }

        return $count;
    }
}
