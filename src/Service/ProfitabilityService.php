<?php

namespace App\Service;

use App\Entity\Bureau;
use App\Entity\Depense;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\VehicleCreditInstallment;
use App\Entity\Voiture;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProfitabilityService
{
    private const ACTIVE_STATUSES = [
        'confirmed', 'confirmee', 'active', 'en_cours', 'encours',
        'completed', 'terminee', 'done', 'termine',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private VoitureRepository      $voitureRepo,
    ) {}

    /** @param int|null $year null means all-time (no year filter) */
    public function getVehicleProfitability(?int $bureauId, ?int $year, ?int $carId = null): array
    {
        $qbCars = $this->voitureRepo->createQueryBuilder('v');
        $qbCars->andWhere('v.deletedAt IS NULL');
        if ($bureauId) $qbCars->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)    $qbCars->andWhere('v.id = :cid')->setParameter('cid', $carId);
        $cars = $qbCars->getQuery()->getResult();

        if (empty($cars)) {
            return ['rows' => [], 'availableYears' => $this->availableYears($bureauId)];
        }

        // Rental count: reservations that started this year (or ever, when $year is null)
        $qbRent = $this->em->createQueryBuilder()
            ->select('v.id as voitureId, COUNT(r.id) as cnt')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->where('r.reservationStatus IN (:statuses)')
            ->setParameter('statuses', self::ACTIVE_STATUSES)
            ->groupBy('v.id');
        if ($year !== null) $qbRent->andWhere('YEAR(r.dateDebut) = :year')->setParameter('year', $year);
        if ($bureauId) $qbRent->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)    $qbRent->andWhere('v.id = :cid')->setParameter('cid', $carId);

        $rentalsByVoiture = [];
        foreach ($qbRent->getQuery()->getResult() as $row) {
            $rentalsByVoiture[(int) $row['voitureId']] = (int) $row['cnt'];
        }

        // Revenue: actual payments received this year (or ever) via reservation → voiture
        $qbRev = $this->em->createQueryBuilder()
            ->select('v.id as voitureId, SUM(p.montant) as revenue')
            ->from(Paiement::class, 'p')
            ->join('p.reservation', 'r')
            ->join('r.voiture', 'v')
            ->where('p.deletedAt IS NULL')
            ->groupBy('v.id');
        if ($year !== null) $qbRev->andWhere('YEAR(p.datePaiement) = :year')->setParameter('year', $year);
        if ($bureauId) $qbRev->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)    $qbRev->andWhere('v.id = :cid')->setParameter('cid', $carId);

        $revenueByVoiture = [];
        foreach ($qbRev->getQuery()->getResult() as $row) {
            $revenueByVoiture[(int) $row['voitureId']] = (float) ($row['revenue'] ?? 0);
        }

        // Operational costs: amounts actually paid (not just committed) on Depense records
        // for this vehicle/year (or ever), by type — kept consistent with revenue/credit below, which
        // are also actual-payment sums, not committed totals.
        $qbCost = $this->em->createQueryBuilder()
            ->select('v.id as voitureId, d.typeDepense, SUM(d.montantPaye) as total, SUM(d.montant) as totalAmt')
            ->from(Depense::class, 'd')
            ->join('d.voiture', 'v')
            ->where('d.deletedAt IS NULL')
            ->andWhere('v.deletedAt IS NULL')
            ->groupBy('v.id, d.typeDepense');
        if ($year !== null) $qbCost->andWhere('YEAR(d.dateDebut) = :year')->setParameter('year', $year);
        if ($bureauId) $qbCost->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)    $qbCost->andWhere('v.id = :cid')->setParameter('cid', $carId);

        $empty = ['repair' => 0.0, 'insurance' => 0.0, 'vignette' => 0.0, 'vidange' => 0.0, 'adblue' => 0.0, 'suivi' => 0.0, 'other' => 0.0];
        $costsByVoiture = [];
        $operationalTotalByVoiture = [];
        foreach ($qbCost->getQuery()->getResult() as $row) {
            $vid = (int) $row['voitureId'];
            if (!isset($costsByVoiture[$vid])) $costsByVoiture[$vid] = $empty;
            $key = match ($row['typeDepense']) {
                'reparation'      => 'repair',
                'assurance'       => 'insurance',
                'vignette'        => 'vignette',
                'vidange'         => 'vidange',
                'adblue'          => 'adblue',
                'suivi_technique' => 'suivi',
                default           => 'other',
            };
            $paid  = (float) ($row['total'] ?? 0);
            $total = (float) ($row['totalAmt'] ?? 0);
            $costsByVoiture[$vid][$key] += $paid;
            // Net profit is computed on the full committed amount (montant), not just what's
            // been paid so far — an unpaid expense still reduces profitability, it just hasn't
            // hit cash yet. Kept separate from $costsByVoiture (paid), which still drives the
            // "Total Expense Paid" card.
            $operationalTotalByVoiture[$vid] = ($operationalTotalByVoiture[$vid] ?? 0.0) + $total;
        }

        // Unpaid remainder (montant - montantPaye): a present-state balance, not tied to the
        // year the expense started, so it is summed across ALL time regardless of $year —
        // otherwise a debt from a prior year would silently disappear from this year's card.
        $qbUnpaid = $this->em->createQueryBuilder()
            ->select('v.id as voitureId, SUM(d.montant - d.montantPaye) as unpaid')
            ->from(Depense::class, 'd')
            ->join('d.voiture', 'v')
            ->where('d.deletedAt IS NULL')
            ->andWhere('v.deletedAt IS NULL')
            ->groupBy('v.id');
        if ($bureauId) $qbUnpaid->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)    $qbUnpaid->andWhere('v.id = :cid')->setParameter('cid', $carId);

        $unpaidByVoiture = [];
        foreach ($qbUnpaid->getQuery()->getResult() as $row) {
            $unpaidByVoiture[(int) $row['voitureId']] = max(0.0, (float) ($row['unpaid'] ?? 0));
        }

        // Credit installments accrued by due date (mirrors how operational costs are committed).
        // Uses dueDate rather than paidAt so costs appear even before "Mark Paid" is clicked.
        $today = new \DateTimeImmutable('today');
        $qbCredit = $this->em->createQueryBuilder()
            ->select('v.id as voitureId, SUM(i.amountDue) as creditPaid')
            ->from(VehicleCreditInstallment::class, 'i')
            ->join('i.vehicleCredit', 'vc')
            ->join('vc.voiture', 'v')
            ->where('i.dueDate <= :today')
            ->andWhere("i.status != 'paid' OR i.paidAt IS NOT NULL")
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('today', $today)
            ->groupBy('v.id');
        if ($year !== null) $qbCredit->andWhere('YEAR(i.dueDate) = :year')->setParameter('year', $year);
        if ($bureauId) $qbCredit->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)    $qbCredit->andWhere('v.id = :cid')->setParameter('cid', $carId);

        $creditByVoiture = [];
        foreach ($qbCredit->getQuery()->getResult() as $row) {
            $creditByVoiture[(int) $row['voitureId']] = (float) ($row['creditPaid'] ?? 0);
        }

        $rows = [];
        foreach ($cars as $car) {
            $vid    = $car->getId();
            $rev    = $revenueByVoiture[$vid] ?? 0.0;
            $rent   = $rentalsByVoiture[$vid] ?? 0;
            $costs  = $costsByVoiture[$vid]   ?? $empty;
            $credit = $creditByVoiture[$vid]  ?? 0.0;
            $unpaid = $unpaidByVoiture[$vid]  ?? 0.0;
            $operationalTotal = $operationalTotalByVoiture[$vid] ?? 0.0;

            $operationalCost = $costs['repair'] + $costs['insurance'] + $costs['vignette']
                             + $costs['vidange'] + $costs['adblue'] + $costs['suivi'] + $costs['other'];
            $totalCost = $operationalCost + $credit;

            if ($rent === 0 && $totalCost === 0.0 && $rev === 0.0 && $unpaid === 0.0) continue;

            // Net profit uses the full committed expense amount (paid + unpaid), not just
            // what's been paid — see comment above where $operationalTotalByVoiture is built.
            $net = $rev - ($operationalTotal + $credit);
            $rows[] = [
                'id'              => $vid,
                'label'           => trim(($car->getMarque() ?? '') . ' ' . ($car->getModele() ?? '')),
                'immatriculation' => $car->getImmatriculation() ?? '',
                'rentals'         => $rent,
                'revenue'         => round($rev, 2),
                'insuranceCost'   => round($costs['insurance'], 2),
                'repairCost'      => round($costs['repair'], 2),
                'vignetteCost'    => round($costs['vignette'], 2),
                'vidangeCost'     => round($costs['vidange'], 2),
                'adblueCost'      => round($costs['adblue'], 2),
                'suiviCost'       => round($costs['suivi'], 2),
                'otherCost'       => round($costs['other'], 2),
                'creditCost'      => round($credit, 2),
                'totalCost'       => round($totalCost, 2),
                'unpaidCost'      => round($unpaid, 2),
                'net'             => round($net, 2),
                'margin'          => $rev > 0 ? (int) round(($net / $rev) * 100) : 0,
            ];
        }

        usort($rows, fn($a, $b) => $b['net'] <=> $a['net']);

        return ['rows' => $rows, 'availableYears' => $this->availableYears($bureauId)];
    }

    public function getBureauProfitability(?int $bureauScopeId, int $year): array
    {
        // Load bureaux
        $qbB = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Bureau::class, 'b')
            ->where('b.deletedAt IS NULL');
        if ($bureauScopeId) $qbB->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $bureaux = $qbB->getQuery()->getResult();

        if (empty($bureaux)) {
            return ['rows' => [], 'availableYears' => $this->availableYears($bureauScopeId)];
        }

        // Revenue: sum of payments on reservations belonging to each bureau
        $qbRev = $this->em->createQueryBuilder()
            ->select('b.id as bureauId, SUM(p.montant) as revenue')
            ->from(Paiement::class, 'p')
            ->join('p.reservation', 'r')
            ->join('r.bureau', 'b')
            ->where('p.deletedAt IS NULL')
            ->andWhere('YEAR(p.datePaiement) = :year')
            ->setParameter('year', $year)
            ->groupBy('b.id');
        if ($bureauScopeId) $qbRev->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $revenueByBureau = [];
        foreach ($qbRev->getQuery()->getResult() as $row) {
            $revenueByBureau[(int) $row['bureauId']] = (float) ($row['revenue'] ?? 0);
        }

        // Vehicle expenses: amounts actually paid, Depense linked to a voiture that belongs to the bureau
        $qbVExp = $this->em->createQueryBuilder()
            ->select('b.id as bureauId, SUM(d.montantPaye) as total')
            ->from(Depense::class, 'd')
            ->join('d.voiture', 'v')
            ->join('v.bureau', 'b')
            ->where('d.deletedAt IS NULL')
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('YEAR(d.dateDebut) = :year')
            ->setParameter('year', $year)
            ->groupBy('b.id');
        if ($bureauScopeId) $qbVExp->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $vehicleExpByBureau = [];
        foreach ($qbVExp->getQuery()->getResult() as $row) {
            $vehicleExpByBureau[(int) $row['bureauId']] = (float) ($row['total'] ?? 0);
        }

        // Office expenses: amounts actually paid, Depense linked directly to bureau (no voiture)
        $qbOExp = $this->em->createQueryBuilder()
            ->select('b.id as bureauId, SUM(d.montantPaye) as total')
            ->from(Depense::class, 'd')
            ->join('d.bureau', 'b')
            ->where('d.deletedAt IS NULL')
            ->andWhere('d.voiture IS NULL')
            ->andWhere('YEAR(d.dateDebut) = :year')
            ->setParameter('year', $year)
            ->groupBy('b.id');
        if ($bureauScopeId) $qbOExp->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $officeExpByBureau = [];
        foreach ($qbOExp->getQuery()->getResult() as $row) {
            $officeExpByBureau[(int) $row['bureauId']] = (float) ($row['total'] ?? 0);
        }

        // Credit installments accrued by due date for vehicles in each bureau
        $today = new \DateTimeImmutable('today');
        $qbCredit = $this->em->createQueryBuilder()
            ->select('b.id as bureauId, SUM(i.amountDue) as creditPaid')
            ->from(VehicleCreditInstallment::class, 'i')
            ->join('i.vehicleCredit', 'vc')
            ->join('vc.voiture', 'v')
            ->join('v.bureau', 'b')
            ->where('i.dueDate <= :today')
            ->andWhere("i.status != 'paid' OR i.paidAt IS NOT NULL")
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('YEAR(i.dueDate) = :year')
            ->setParameter('today', $today)
            ->setParameter('year', $year)
            ->groupBy('b.id');
        if ($bureauScopeId) $qbCredit->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $creditByBureau = [];
        foreach ($qbCredit->getQuery()->getResult() as $row) {
            $creditByBureau[(int) $row['bureauId']] = (float) ($row['creditPaid'] ?? 0);
        }

        // Rental count this year per bureau
        $qbRent = $this->em->createQueryBuilder()
            ->select('b.id as bureauId, COUNT(r.id) as cnt')
            ->from(Reservation::class, 'r')
            ->join('r.bureau', 'b')
            ->where('r.reservationStatus IN (:statuses)')
            ->andWhere('YEAR(r.dateDebut) = :year')
            ->setParameter('statuses', self::ACTIVE_STATUSES)
            ->setParameter('year', $year)
            ->groupBy('b.id');
        if ($bureauScopeId) $qbRent->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $rentalsByBureau = [];
        foreach ($qbRent->getQuery()->getResult() as $row) {
            $rentalsByBureau[(int) $row['bureauId']] = (int) $row['cnt'];
        }

        // Total vehicle count per bureau (fleet size, not year-filtered)
        $qbFleet = $this->em->createQueryBuilder()
            ->select('b.id as bureauId, COUNT(v.id) as cnt')
            ->from(Voiture::class, 'v')
            ->join('v.bureau', 'b')
            ->andWhere('v.deletedAt IS NULL')
            ->groupBy('b.id');
        if ($bureauScopeId) $qbFleet->andWhere('b.id = :bid')->setParameter('bid', $bureauScopeId);
        $fleetByBureau = [];
        foreach ($qbFleet->getQuery()->getResult() as $row) {
            $fleetByBureau[(int) $row['bureauId']] = (int) $row['cnt'];
        }

        $rows = [];
        foreach ($bureaux as $bureau) {
            $bid     = $bureau->getId();
            $rev     = $revenueByBureau[$bid]   ?? 0.0;
            $vExp    = $vehicleExpByBureau[$bid] ?? 0.0;
            $oExp    = $officeExpByBureau[$bid]  ?? 0.0;
            $credit  = $creditByBureau[$bid]     ?? 0.0;
            $rentals = $rentalsByBureau[$bid]    ?? 0;
            $fleet   = $fleetByBureau[$bid]      ?? 0;

            $totalExp = $vExp + $oExp + $credit;
            if ($rev === 0.0 && $totalExp === 0.0 && $rentals === 0) continue;

            $profit = $rev - $totalExp;
            $manager = $bureau->getManager();
            $rows[] = [
                'id'              => $bid,
                'name'            => $bureau->getNom() ?? "Bureau #$bid",
                'address'         => $bureau->getAdresse() ?? '',
                'manager'         => $manager ? trim(($manager->getPrenom() ?? '') . ' ' . ($manager->getNom() ?? '')) ?: $manager->getEmail() : null,
                'vehicleCount'    => $fleet,
                'rentals'         => $rentals,
                'revenue'         => round($rev, 2),
                'vehicleExpenses' => round($vExp, 2),
                'officeExpenses'  => round($oExp, 2),
                'creditCost'      => round($credit, 2),
                'totalExpenses'   => round($totalExp, 2),
                'profit'          => round($profit, 2),
                'margin'          => $rev > 0 ? (int) round(($profit / $rev) * 100) : 0,
            ];
        }

        usort($rows, fn($a, $b) => $b['profit'] <=> $a['profit']);

        return ['rows' => $rows, 'availableYears' => $this->availableYears($bureauScopeId)];
    }

    private function availableYears(?int $bureauId): array
    {
        $years = [];

        $qb = $this->em->createQueryBuilder()
            ->select('YEAR(r.dateDebut) as yr')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->where('r.dateDebut IS NOT NULL')
            ->groupBy('yr');
        if ($bureauId) $qb->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        foreach ($qb->getQuery()->getResult() as $row) {
            if ($row['yr']) $years[(int) $row['yr']] = true;
        }

        $qb2 = $this->em->createQueryBuilder()
            ->select('YEAR(d.dateDebut) as yr')
            ->from(Depense::class, 'd')
            ->join('d.voiture', 'v')
            ->where('d.dateDebut IS NOT NULL')
            ->groupBy('yr');
        if ($bureauId) $qb2->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        foreach ($qb2->getQuery()->getResult() as $row) {
            if ($row['yr']) $years[(int) $row['yr']] = true;
        }

        $years[(int) date('Y')] = true;
        $result = array_keys($years);
        rsort($result);
        return $result;
    }
}
