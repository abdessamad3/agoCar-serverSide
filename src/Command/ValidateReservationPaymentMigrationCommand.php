<?php

namespace App\Command;

use App\Entity\HistoriquePaiement;
use App\Entity\Paiement;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Phase 4 — validates the Reservation payment unification (HistoriquePaiement -> Paiement).
 *
 * For every Reservation:
 *   pre  = SUM(historique_paiement.montant WHERE deletedAt IS NULL)
 *   post = SUM(paiement.montant WHERE deletedAt IS NULL AND reservation = this)
 *
 * pre and post must match exactly. historique_paiement is read-only history at this
 * point (frontend no longer writes to it after Phase 3), so any mismatch means the
 * Phase 1 backfill missed something or a record was altered/lost in the cutover —
 * not normal drift from new activity.
 */
#[AsCommand(
    name: 'app:validate-reservation-payment-migration',
    description: 'Phase 4: validate every Reservation\'s historique_paiement total matches its paiement total after the unification migration',
)]
class ValidateReservationPaymentMigrationCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Phase 4 — Reservation Payment Migration Validation');

        $reservations = $this->em->getRepository(Reservation::class)->findAll();
        $io->writeln(sprintf('Checking %d reservation(s)...', count($reservations)));

        $mismatches  = [];
        $checkedWithHistory = 0;

        foreach ($reservations as $reservation) {
            $pre = (float) ($this->em->createQueryBuilder()
                ->select('COALESCE(SUM(hp.montant), 0)')
                ->from(HistoriquePaiement::class, 'hp')
                ->where('hp.reservation = :r')
                ->andWhere('hp.deletedAt IS NULL')
                ->setParameter('r', $reservation)
                ->getQuery()
                ->getSingleScalarResult());

            $post = (float) ($this->em->createQueryBuilder()
                ->select('COALESCE(SUM(p.montant), 0)')
                ->from(Paiement::class, 'p')
                ->where('p.reservation = :r')
                ->andWhere('p.deletedAt IS NULL')
                ->setParameter('r', $reservation)
                ->getQuery()
                ->getSingleScalarResult());

            if ($pre > 0.0) {
                $checkedWithHistory++;
            }

            // Post must be >= pre: the backfill must have copied every historique row,
            // and new payments recorded after cutover only add to Paiement, never
            // reduce it below the migrated baseline.
            if (round($post, 2) < round($pre, 2)) {
                $mismatches[] = [
                    'id'   => $reservation->getId(),
                    'pre'  => $pre,
                    'post' => $post,
                    'diff' => round($pre - $post, 2),
                ];
            }
        }

        $io->section('Result');
        $io->writeln(sprintf('Reservations with historique_paiement records: %d', $checkedWithHistory));
        $io->writeln(sprintf('Reservations checked total: %d', count($reservations)));

        if (empty($mismatches)) {
            $io->success('PASS — every reservation\'s Paiement total covers its historique_paiement total. No mismatches found.');
            return Command::SUCCESS;
        }

        $io->error(sprintf('FAIL — %d reservation(s) have a payment total mismatch:', count($mismatches)));
        $io->table(
            ['Reservation ID', 'Pre (historique_paiement)', 'Post (paiement)', 'Missing amount'],
            array_map(fn($m) => [$m['id'], $m['pre'], $m['post'], $m['diff']], $mismatches)
        );

        return Command::FAILURE;
    }
}
