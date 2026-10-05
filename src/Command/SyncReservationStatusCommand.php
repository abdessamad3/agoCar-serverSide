<?php

namespace App\Command;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Auto-advances reservation statuses based on date boundaries.
 *
 * confirmee  + dateDebut <= now              → en_cours
 * en_cours   + dateFin  <  now              → terminee
 *
 * Run every few minutes via cron:
 *   * * * * * php /path/to/project/bin/console reservation:sync-status >> /var/log/reservation_sync.log 2>&1
 */
#[AsCommand(
    name:        'reservation:sync-status',
    description: 'Auto-transitions confirmee→en_cours and en_cours→terminee based on dates.',
)]
class SyncReservationStatusCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $started  = $this->transition(
            fromStatus: 'confirmee',
            toStatus:   'en_cours',
            condition:  'r.dateDebut <= :now AND r.dateFin >= :now',
            now:        $now,
        );

        $finished = $this->transition(
            fromStatus: 'en_cours',
            toStatus:   'terminee',
            condition:  'r.dateFin < :now',
            now:        $now,
        );

        $this->em->flush();

        $io->success(sprintf(
            '%d confirmee→en_cours, %d en_cours→terminee.',
            $started, $finished
        ));

        return Command::SUCCESS;
    }

    private function transition(
        string $fromStatus,
        string $toStatus,
        string $condition,
        \DateTimeImmutable $now,
    ): int {
        $reservations = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.reservationStatus = :from')
            ->andWhere($condition)
            ->setParameter('from', $fromStatus)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($reservations as $r) {
            $r->setReservationStatus($toStatus);
        }

        return count($reservations);
    }
}
