<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Returns true if the vehicle already has a non-cancelled reservation that
     * overlaps [start, end]. Pass $excludeId to ignore the current reservation
     * when checking on an update.
     */
    public function hasOverlap(
        int $voitureId,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?int $excludeId = null
    ): bool {
        // Reservation status is stored under multiple spellings across this codebase
        // (confirmed elsewhere: DashboardController, ProfitabilityService) — checking
        // only 'annule' here let a reservation cancelled as 'annulee' or 'cancelled'
        // keep blocking new bookings for its dates.
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.voiture = :voitureId')
            ->andWhere('r.dateDebut < :end')
            ->andWhere('r.dateFin > :start')
            ->andWhere('r.reservationStatus NOT IN (:cancelled)')
            ->setParameter('voitureId', $voitureId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', ['annule', 'annulee', 'cancelled']);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** Sum of money still owed across all non-cancelled reservations for one client. */
    public function getClientOutstandingDebt(int $clientId): float
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.total', 'r.montantPaye')
            ->where('r.client = :clientId')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.reservationStatus NOT IN (:cancelled)')
            ->setParameter('clientId', $clientId)
            ->setParameter('cancelled', ['annule', 'annulee', 'cancelled'])
            ->getQuery()->getArrayResult();

        return self::sumOwed($rows);
    }

    /** Same as getClientOutstandingDebt() but for several clients at once (one query instead of
     *  one-per-row) — for list views that need to show a debt badge per client. Debt is a
     *  property of the client, not the bureau, so this is intentionally NOT bureau-filtered —
     *  callers pass the client IDs already visible on the page (already bureau-scoped upstream). */
    public function getOutstandingDebtForClients(array $clientIds): array
    {
        if (!$clientIds) {
            return [];
        }

        $qb = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.client) AS clientId', 'r.total', 'r.montantPaye')
            ->where('r.deletedAt IS NULL')
            ->andWhere('r.reservationStatus NOT IN (:cancelled)')
            ->andWhere('r.client IN (:clientIds)')
            ->setParameter('cancelled', ['annule', 'annulee', 'cancelled'])
            ->setParameter('clientIds', $clientIds);

        $byClient = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $byClient[$row['clientId']][] = $row;
        }

        $debts = [];
        foreach ($byClient as $clientId => $rows) {
            $debt = self::sumOwed($rows);
            if ($debt > 0) {
                $debts[$clientId] = $debt;
            }
        }
        return $debts;
    }

    /** Full financial picture for one client, for the Client Detail page. totalRevenue/
     *  outstandingDebt/overpayments are scoped to CLOSED reservations only (final totals);
     *  totalRentals/totalPaid count every non-cancelled reservation (money collected is real
     *  even on a rental that's still in progress). */
    public function getClientFinancialSummary(int $clientId): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.total', 'r.montantPaye', 'r.reservationStatus')
            ->where('r.client = :clientId')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.reservationStatus NOT IN (:cancelled)')
            ->setParameter('clientId', $clientId)
            ->setParameter('cancelled', ['annule', 'annulee', 'cancelled'])
            ->getQuery()->getArrayResult();

        $totalRevenue = 0.0;
        $totalPaid    = 0.0;
        $debt         = 0.0;
        $overpaid     = 0.0;

        foreach ($rows as $row) {
            $total = (float) $row['total'];
            $paid  = (float) $row['montantPaye'];
            $totalPaid += $paid;

            if (in_array($row['reservationStatus'], Reservation::CLOSED_STATUSES, true)) {
                $totalRevenue += $total;
                $balance = $paid - $total;
                if ($balance < 0) $debt     += -$balance;
                if ($balance > 0) $overpaid += $balance;
            }
        }

        return [
            'totalRentals'    => count($rows),
            'totalRevenue'    => $totalRevenue,
            'totalPaid'       => $totalPaid,
            'outstandingDebt' => $debt,
            'overpayments'    => $overpaid,
        ];
    }

    /**
     * Returns all pending reservations for the same car that overlap [start, end],
     * excluding the reservation being confirmed ($excludeId).
     *
     * @return Reservation[]
     */
    public function findConflictingPending(
        int $voitureId,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $excludeId
    ): array {
        return $this->createQueryBuilder('r')
            ->where('r.voiture = :voitureId')
            ->andWhere('r.dateDebut < :end')
            ->andWhere('r.dateFin > :start')
            ->andWhere('r.reservationStatus = :pending')
            ->andWhere('r.id != :excludeId')
            ->setParameter('voitureId', $voitureId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('pending', 'pending')
            ->setParameter('excludeId', $excludeId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns true if the car has a confirmed or in_progress reservation
     * overlapping [start, end], excluding $excludeId.
     */
    public function hasConfirmedOverlap(
        int $voitureId,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $excludeId
    ): bool {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.voiture = :voitureId')
            ->andWhere('r.dateDebut < :end')
            ->andWhere('r.dateFin > :start')
            ->andWhere('r.reservationStatus IN (:active)')
            ->andWhere('r.id != :excludeId')
            ->setParameter('voitureId', $voitureId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('active', ['confirmed', 'in_progress', 'en_cours'])
            ->setParameter('excludeId', $excludeId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private static function sumOwed(array $rows): float
    {
        $debt = 0.0;
        foreach ($rows as $row) {
            $balance = (float) $row['montantPaye'] - (float) $row['total'];
            if ($balance < 0) {
                $debt += -$balance;
            }
        }
        return $debt;
    }
}
