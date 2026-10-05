<?php

namespace App\Service;

use App\Entity\Contrat;
use App\Entity\Credit;
use App\Entity\Depense;
use App\Entity\Reparation;
use App\Entity\Reservation;
use App\Entity\Voiture;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardAggregatorService
{
    private const REVENUE_STATUSES = ['confirmed', 'confirmee', 'active', 'en_cours', 'encours', 'completed', 'terminee', 'done', 'termine'];

    public function __construct(
        private EntityManagerInterface $em,
        private VoitureRepository      $voitureRepo,
        private ComplianceService      $complianceService,
        private OilChangeService       $oilChangeService,
    ) {}

    public function aggregate(?int $bureauId): array
    {
        return [
            'kpi'                => $this->kpi($bureauId),
            'paymentDistribution'=> $this->paymentDistribution($bureauId),
            'revenueByMonth'     => $this->revenueByMonth($bureauId),
            'expensesByMonth'    => $this->expensesByMonth($bureauId),
            'bookingsByMonth'    => $this->bookingsByMonth($bureauId),
            'recentReservations' => $this->recentReservations($bureauId),
            'recentContracts'    => $this->recentContracts($bureauId),
            'credits'            => $this->creditKpis($bureauId),
            'compliance'         => $this->complianceKpis($bureauId),
            'carsInRepair'       => $this->carsInRepair($bureauId),
            'oilReminders'       => $this->oilChangeService->getDashboardSummary($bureauId ?? 0),
        ];
    }

    private function kpi(?int $bureauId): array
    {
        $qbBase = fn() => $this->em->createQueryBuilder()->from(Voiture::class, 'v');
        $now = new \DateTimeImmutable();

        $qbTotal = $qbBase()->select('COUNT(v.id)');
        if ($bureauId) $qbTotal->where('v.bureau = :b')->setParameter('b', $bureauId);
        $totalCars = (int) $qbTotal->getQuery()->getSingleScalarResult();

        $qbStatus = $this->em->createQueryBuilder()
            ->select('v.voitureStatus, COUNT(v.id) as cnt')
            ->from(Voiture::class, 'v')
            ->groupBy('v.voitureStatus');
        if ($bureauId) $qbStatus->where('v.bureau = :b')->setParameter('b', $bureauId);
        $statusCounts = $qbStatus->getQuery()->getResult();

        $byStatus = [];
        foreach ($statusCounts as $row) { $byStatus[$row['voitureStatus']] = (int) $row['cnt']; }

        $today = new \DateTimeImmutable('today');

        // Live: distinct cars with an active repair today
        $qbMaint = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT v.id)')
            ->from(Reparation::class, 'rep')
            ->join('rep.depense', 'd')
            ->join('d.voiture', 'v')
            ->where('rep.deletedAt IS NULL')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('today', $today);
        if ($bureauId) $qbMaint->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        $maintenanceCars = (int) $qbMaint->getQuery()->getSingleScalarResult();

        // Live: distinct cars with an active reservation today (not cancelled)
        $qbBooked = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT v.id)')
            ->from(Reservation::class, 'res')
            ->join('res.voiture', 'v')
            ->where('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut <= :today')
            ->andWhere('res.dateFin >= :today')
            ->setParameter('cancelled', ['annulee', 'annule', 'cancelled'])
            ->setParameter('today', $today);
        if ($bureauId) $qbBooked->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        $bookedCars = (int) $qbBooked->getQuery()->getSingleScalarResult();

        $blockedCars   = ($byStatus['vendu'] ?? 0) + ($byStatus['archive'] ?? 0) + ($byStatus['hors_service'] ?? 0);
        $availableCars = max(0, $totalCars - $blockedCars - $maintenanceCars - $bookedCars);

        // Active bookings (currently running)
        $qbActive = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->where('r.dateDebut <= :now')->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now);
        if ($bureauId) $qbActive->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        $activeBookings = (int) $qbActive->getQuery()->getSingleScalarResult();

        // Total revenue (confirmed/done reservations)
        $qbRev = $this->em->createQueryBuilder()
            ->select('SUM(r.total)')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->where('r.reservationStatus IN (:statuses)')
            ->setParameter('statuses', self::REVENUE_STATUSES);
        if ($bureauId) $qbRev->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        $totalRevenue = (float) ($qbRev->getQuery()->getSingleScalarResult() ?? 0);

        // Total expenses
        $qbExp = $this->em->createQueryBuilder()
            ->select('SUM(d.montant)')
            ->from(Depense::class, 'd')
            ->leftJoin('d.voiture', 'v')
            ->where('v.id IS NULL OR v.deletedAt IS NULL');
        if ($bureauId) $qbExp->andWhere('d.bureau = :b OR v.bureau = :b')->setParameter('b', $bureauId);
        $totalExpenses = (float) ($qbExp->getQuery()->getSingleScalarResult() ?? 0);

        // Total clients (distinct from reservations) — INNER JOIN excludes null clients
        $qbClients = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT c.id)')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->join('r.client', 'c');
        if ($bureauId) $qbClients->where('v.bureau = :b')->setParameter('b', $bureauId);
        $totalClients = (int) $qbClients->getQuery()->getSingleScalarResult();

        return [
            'totalCars'       => $totalCars,
            'totalClients'    => $totalClients,
            'availableCars'   => $availableCars,
            'bookedCars'      => $bookedCars,
            'maintenanceCars' => $maintenanceCars,
            'occupancyRate'   => $totalCars > 0 ? round($bookedCars / $totalCars * 100) : 0,
            'activeBookings'  => $activeBookings,
            'totalRevenue'    => $totalRevenue,
            'totalExpenses'   => $totalExpenses,
        ];
    }

    private function paymentDistribution(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r.total, r.montantPaye')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v');
        if ($bureauId) $qb->where('v.bureau = :b')->setParameter('b', $bureauId);
        $rows = $qb->getQuery()->getResult();

        $paid = $partial = $unpaid = 0;
        foreach ($rows as $row) {
            $total = (float) $row['total'];
            $mp    = (float) $row['montantPaye'];
            if ($total > 0 && $mp >= $total)        $paid++;
            elseif ($mp > 0 && $mp < $total)         $partial++;
            else                                     $unpaid++;
        }
        return ['paidCount' => $paid, 'partialCount' => $partial, 'unpaidCount' => $unpaid];
    }

    private function revenueByMonth(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('YEAR(r.dateDebut) as yr, MONTH(r.dateDebut) as mo, SUM(r.total) as revenue')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->where('r.reservationStatus IN (:statuses)')
            ->andWhere('r.dateDebut >= :since')
            ->setParameter('statuses', self::REVENUE_STATUSES)
            ->setParameter('since', new \DateTimeImmutable('-12 months'))
            ->groupBy('yr, mo')
            ->orderBy('yr', 'ASC')
            ->addOrderBy('mo', 'ASC');
        if ($bureauId) $qb->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        return $qb->getQuery()->getResult();
    }

    private function expensesByMonth(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('YEAR(d.dateDebut) as yr, MONTH(d.dateDebut) as mo, SUM(d.montant) as expenses')
            ->from(Depense::class, 'd')
            ->leftJoin('d.voiture', 'v')
            ->where('d.dateDebut >= :since')
            ->andWhere('v.id IS NULL OR v.deletedAt IS NULL')
            ->setParameter('since', new \DateTimeImmutable('-12 months'))
            ->groupBy('yr, mo')
            ->orderBy('yr', 'ASC')
            ->addOrderBy('mo', 'ASC');
        if ($bureauId) $qb->andWhere('d.bureau = :b OR v.bureau = :b')->setParameter('b', $bureauId);
        return $qb->getQuery()->getResult();
    }

    private function bookingsByMonth(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('YEAR(r.dateDebut) as yr, MONTH(r.dateDebut) as mo, COUNT(r.id) as count')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->where('r.dateDebut >= :since')
            ->setParameter('since', new \DateTimeImmutable('-12 months'))
            ->groupBy('yr, mo')
            ->orderBy('yr', 'ASC')
            ->addOrderBy('mo', 'ASC');
        if ($bureauId) $qb->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        return $qb->getQuery()->getResult();
    }

    private function recentReservations(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->orderBy('r.dateDebut', 'DESC')
            ->setMaxResults(10);
        if ($bureauId) $qb->where('v.bureau = :b')->setParameter('b', $bureauId);

        return array_map(fn(Reservation $r) => [
            'id'                => $r->getId(),
            'client'            => ['id' => $r->getClient()?->getId(), 'nom' => $r->getClient()?->getNom()],
            'voiture'           => ['id' => $r->getVoiture()?->getId(), 'marque' => $r->getVoiture()?->getMarque(), 'modele' => $r->getVoiture()?->getModele()],
            'dateDebut'         => $r->getDateDebut()?->format('Y-m-d H:i:s'),
            'dateFin'           => $r->getDateFin()?->format('Y-m-d H:i:s'),
            'total'             => $r->getTotal(),
            'montantPaye'       => $r->getMontantPaye(),
            'reservationStatus' => $r->getReservationStatus(),
            'creeAu'            => $r->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $qb->getQuery()->getResult());
    }

    private function recentContracts(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Contrat::class, 'c')
            ->join('c.reservation', 'r')
            ->join('r.voiture', 'v')
            ->orderBy('c.creeAu', 'DESC')
            ->setMaxResults(5);
        if ($bureauId) $qb->where('v.bureau = :b')->setParameter('b', $bureauId);

        return array_map(fn(Contrat $c) => [
            'id'        => $c->getId(),
            'client'    => ['id' => $c->getReservation()?->getClient()?->getId(), 'nom' => $c->getReservation()?->getClient()?->getNom()],
            'voiture'   => $c->getReservation()?->getVoiture() ? [
                'marque' => $c->getReservation()->getVoiture()->getMarque(),
                'modele' => $c->getReservation()->getVoiture()->getModele(),
            ] : null,
            'dateDebut' => $c->getReservation()?->getDateDebut()?->format('Y-m-d'),
            'dateFin'   => $c->getReservation()?->getDateFin()?->format('Y-m-d'),
            'creeAu'    => $c->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $qb->getQuery()->getResult());
    }

    private function creditKpis(?int $bureauId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Credit::class, 'c')
            ->join('c.voiture', 'v');
        if ($bureauId) $qb->where('v.bureau = :b')->setParameter('b', $bureauId);
        $credits = $qb->getQuery()->getResult();

        $active = $overdue = 0;
        $monthlyDue = $debtTotal = 0.0;
        $now = new \DateTimeImmutable();

        foreach ($credits as $c) {
            if ($c->getStatut() === 'en_cours')   { $active++; }
            if ($c->getStatut() === 'en_retard')  { $overdue++; }
            if ($c->getStatut() === 'en_cours') {
                $monthlyDue += (float) $c->getMensualite();
                $totalDue = max(0, ((float) $c->getMontantTotal()) - 0); // apport may not exist
                $mensualite = (float) $c->getMensualite();
                $dureeMois  = (int) $c->getDureeMois();
                $dateDebut  = $c->getDateDebut();
                if ($dateDebut && $mensualite > 0) {
                    $passed = ($now->format('Y') - $dateDebut->format('Y')) * 12
                            + ($now->format('n') - $dateDebut->format('n'));
                    $paidMonths = min(max(0, $passed), $dureeMois);
                    $debtTotal += max(0, $totalDue - $paidMonths * $mensualite);
                } else {
                    $debtTotal += $totalDue;
                }
            }
        }

        return ['active' => $active, 'overdue' => $overdue, 'monthlyDue' => $monthlyDue, 'debtTotal' => $debtTotal];
    }

    private function complianceKpis(?int $bureauId): array
    {
        $qb = $this->voitureRepo->createQueryBuilder('v');
        if ($bureauId) $qb->where('v.bureau = :b')->setParameter('b', $bureauId);
        $cars = $qb->getQuery()->getResult();

        $compliant = $warning = $critical = $blocked = 0;
        $expiringSoon30 = $critical7Days = [];

        foreach ($cars as $car) {
            $status = $this->complianceService->getComplianceStatus($car);
            switch ($status['overall']) {
                case ComplianceService::VALID:    $compliant++; break;
                case ComplianceService::WARNING:  $warning++;   break;
                case ComplianceService::CRITICAL: $critical++;  break;
                case ComplianceService::EXPIRED:  $blocked++;   break;
            }

            $label = trim(($car->getMarque() ?? '') . ' ' . ($car->getModele() ?? ''));
            $docMap = ['vignette' => 'Vignette', 'assurance' => 'Assurance', 'visite' => 'Visite tech.'];
            foreach ($docMap as $key => $docLabel) {
                $info = $status[$key] ?? null;
                if (!$info || $info['daysRemaining'] === null) continue;
                if ($info['status'] === ComplianceService::NOT_REQUIRED) continue;
                if ($info['status'] === ComplianceService::UPCOMING) continue; // daysRemaining here means days-until-start, not days-until-expiry
                $days = $info['daysRemaining'];
                $carObj = ['id' => $car->getId(), 'marque' => $car->getMarque(), 'modele' => $car->getModele(), 'immatriculation' => $car->getImmatriculation()];
                if ($days >= 0 && $days <= 30) {
                    $expiringSoon30[] = ['car' => $carObj, 'doc' => $docLabel, 'days' => $days];
                }
                if ($days >= 0 && $days <= 7) {
                    $critical7Days[] = ['car' => $carObj, 'doc' => $docLabel, 'days' => $days];
                }
            }
        }

        usort($expiringSoon30, fn($a, $b) => $a['days'] - $b['days']);
        usort($critical7Days,  fn($a, $b) => $a['days'] - $b['days']);

        return compact('compliant', 'warning', 'critical', 'blocked', 'expiringSoon30', 'critical7Days');
    }

    private function carsInRepair(?int $bureauId): array
    {
        $today = new \DateTimeImmutable('today');
        $qb = $this->em->createQueryBuilder()
            ->select('rep')
            ->from(Reparation::class, 'rep')
            ->join('rep.depense', 'd')
            ->leftJoin('d.voiture', 'v')
            ->where('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('today', $today);
        if ($bureauId) $qb->andWhere('v.bureau = :b OR d.bureau = :b')->setParameter('b', $bureauId);

        return array_map(fn(Reparation $rep) => [
            'repId'               => $rep->getId(),
            'carId'               => $rep->getDepense()?->getVoiture()?->getId(),
            'marque'              => $rep->getDepense()?->getVoiture()?->getMarque(),
            'modele'              => $rep->getDepense()?->getVoiture()?->getModele(),
            'immatriculation'     => $rep->getDepense()?->getVoiture()?->getImmatriculation(),
            'descriptionTechnique'=> $rep->getDescriptionTechnique(),
            'dateDebut'           => $rep->getDepense()?->getDateDebut()?->format('Y-m-d'),
            'dateFin'             => $rep->getDepense()?->getDateFin()?->format('Y-m-d'),
        ], $qb->getQuery()->getResult());
    }
}
