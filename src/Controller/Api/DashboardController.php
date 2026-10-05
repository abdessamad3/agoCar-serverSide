<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\VehicleCreditInstallment;
use App\Repository\VoitureRepository;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\DepenseRepository;
use App\Service\DashboardAggregatorService;
use App\Service\OilChangeService;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/dashboard', name: 'app_api_dashboard_')]
class DashboardController extends AbstractController
{
    use BureauAwareTrait;

    public function __construct(
        private VoitureRepository          $voitureRepo,
        private ReservationRepository      $reservationRepo,
        private ClientRepository           $clientRepo,
        private DepenseRepository          $depenseRepo,
        private OilChangeService           $oilChangeService,
        private DashboardAggregatorService $aggregator,
        private EntityManagerInterface     $em,
    ) {}

    #[Route('/aggregate', name: 'aggregate', methods: ['GET'])]
    public function aggregate(): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        return $this->json($this->aggregator->aggregate($bureauId ?: null));
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        $bureauId = (int) $request->query->get('bureauId', 0);
        $now = new \DateTime();

        if ($bureauId) {
            $totalVoitures = (int) $this->voitureRepo->createQueryBuilder('v')
                ->select('COUNT(v.id)')->where('v.bureau = :bid')->setParameter('bid', $bureauId)
                ->getQuery()->getSingleScalarResult();
            $availableVoitures = (int) $this->voitureRepo->createQueryBuilder('v')
                ->select('COUNT(v.id)')->where('v.bureau = :bid AND v.voitureStatus = :s')
                ->setParameter('bid', $bureauId)->setParameter('s', 'available')
                ->getQuery()->getSingleScalarResult();
            $totalClients = count(array_unique(array_column(
                $this->em->createQueryBuilder()
                    ->select('c.id as cid')
                    ->from(Reservation::class, 'r')
                    ->join('r.voiture', 'v')
                    ->join('r.client', 'c')
                    ->where('v.bureau = :bid')
                    ->setParameter('bid', $bureauId)->getQuery()->getScalarResult(),
                'cid'
            )));
        } else {
            $totalVoitures     = $this->voitureRepo->count([]);
            $availableVoitures = $this->voitureRepo->count(['voitureStatus' => 'available']);
            $totalClients      = $this->clientRepo->count([]);
        }

        $qbActive = $this->reservationRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')->where('r.dateDebut <= :now')->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now);
        if ($bureauId) {
            $qbActive->join('r.voiture', 'bv')->andWhere('bv.bureau = :bid')->setParameter('bid', $bureauId);
        }
        $activeReservations = (int) $qbActive->getQuery()->getSingleScalarResult();

        $qbRev = $this->reservationRepo->createQueryBuilder('r')->select('SUM(r.total)');
        if ($bureauId) {
            $qbRev->join('r.voiture', 'bv')->where('bv.bureau = :bid')->setParameter('bid', $bureauId);
        }
        $revenue = (float) ($qbRev->getQuery()->getSingleScalarResult() ?? 0);

        $qbExp = $this->depenseRepo->createQueryBuilder('d')->select('SUM(d.montant)');
        if ($bureauId) {
            $qbExp->leftJoin('d.voiture', 'v')
                  ->where('d.bureau = :bid OR v.bureau = :bid')
                  ->setParameter('bid', $bureauId);
        }
        $expenses = (float) ($qbExp->getQuery()->getSingleScalarResult() ?? 0);

        $utilizationRate = $totalVoitures > 0
            ? round((($totalVoitures - $availableVoitures) / $totalVoitures) * 100, 1)
            : 0;

        return $this->json([
            'totalVoitures'      => $totalVoitures,
            'availableVoitures'  => $availableVoitures,
            'activeReservations' => $activeReservations,
            'totalClients'       => $totalClients,
            'revenue'            => $revenue,
            'expenses'           => $expenses,
            'netProfit'          => $revenue - $expenses,
            'utilizationRate'    => $utilizationRate,
        ]);
    }

    #[Route('/voitures-status', name: 'voitures_status', methods: ['GET'])]
    public function getVoituresStatus(Request $request): JsonResponse
    {
        $bureauId = (int) $request->query->get('bureauId', 0);
        $qb = $this->voitureRepo->createQueryBuilder('v')
            ->select('v.voitureStatus, COUNT(v.id) as count')
            ->groupBy('v.voitureStatus');
        if ($bureauId) {
            $qb->where('v.bureau = :bid')->setParameter('bid', $bureauId);
        }

        $statusBreakdown = [];
        foreach ($qb->getQuery()->getResult() as $r) {
            $statusBreakdown[$r['voitureStatus']] = (int) $r['count'];
        }

        return $this->json(['voituresByStatus' => $statusBreakdown]);
    }

    #[Route('/reservations-status', name: 'reservations_status', methods: ['GET'])]
    public function getReservationsStatus(Request $request): JsonResponse
    {
        $bureauId = (int) $request->query->get('bureauId', 0);
        $now = new \DateTime();

        $makeQb = function () use ($bureauId): \Doctrine\ORM\QueryBuilder {
            $qb = $this->reservationRepo->createQueryBuilder('r');
            if ($bureauId) {
                $qb->join('r.voiture', 'bv')->andWhere('bv.bureau = :bid')->setParameter('bid', $bureauId);
            }
            return $qb;
        };

        $active = (int) $makeQb()->select('COUNT(r.id)')
            ->andWhere('r.dateDebut <= :now')->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now)->getQuery()->getSingleScalarResult();

        $upcoming = (int) $makeQb()->select('COUNT(r.id)')
            ->andWhere('r.dateDebut > :now')
            ->setParameter('now', $now)->getQuery()->getSingleScalarResult();

        $past = (int) $makeQb()->select('COUNT(r.id)')
            ->andWhere('r.dateFin < :now')
            ->setParameter('now', $now)->getQuery()->getSingleScalarResult();

        return $this->json([
            'active'   => $active,
            'upcoming' => $upcoming,
            'past'     => $past,
            'total'    => $active + $upcoming + $past,
        ]);
    }

    #[Route('/revenue-summary', name: 'revenue_summary', methods: ['GET'])]
    public function getRevenueSummary(Request $request): JsonResponse
    {
        $bureauId     = (int) $request->query->get('bureauId', 0);
        $startOfMonth = (new \DateTime('first day of this month'))->setTime(0, 0, 0);
        $endOfMonth   = (new \DateTime('last day of this month'))->setTime(23, 59, 59);
        $startOfYear  = new \DateTime('first day of January this year');
        $endOfYear    = new \DateTime('last day of December this year');

        $makeResQb = function () use ($bureauId): \Doctrine\ORM\QueryBuilder {
            $qb = $this->reservationRepo->createQueryBuilder('r');
            if ($bureauId) {
                $qb->join('r.voiture', 'bv')->andWhere('bv.bureau = :bid')->setParameter('bid', $bureauId);
            }
            return $qb;
        };

        $makeDepQb = function () use ($bureauId): \Doctrine\ORM\QueryBuilder {
            $qb = $this->depenseRepo->createQueryBuilder('d');
            if ($bureauId) {
                $qb->leftJoin('d.voiture', 'v')
                   ->where('d.bureau = :bid OR v.bureau = :bid')
                   ->setParameter('bid', $bureauId);
            }
            return $qb;
        };

        $totalRevenue = (float) ($makeResQb()->select('SUM(r.total)')->getQuery()->getSingleScalarResult() ?? 0);

        $monthRevenue = (float) ($makeResQb()->select('SUM(r.total)')
            ->andWhere('r.creeAu >= :s')->andWhere('r.creeAu <= :e')
            ->setParameter('s', $startOfMonth)->setParameter('e', $endOfMonth)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $yearRevenue = (float) ($makeResQb()->select('SUM(r.total)')
            ->andWhere('r.creeAu >= :s')->andWhere('r.creeAu <= :e')
            ->setParameter('s', $startOfYear)->setParameter('e', $endOfYear)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $totalExpenses = (float) ($makeDepQb()->select('SUM(d.montant)')->getQuery()->getSingleScalarResult() ?? 0);

        $monthExpenses = (float) ($makeDepQb()->select('SUM(d.montant)')
            ->andWhere('d.creeAu >= :s')->andWhere('d.creeAu <= :e')
            ->setParameter('s', $startOfMonth)->setParameter('e', $endOfMonth)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $monthReservations = (int) ($makeResQb()->select('COUNT(r.id)')
            ->andWhere('r.creeAu >= :s')->andWhere('r.creeAu <= :e')
            ->setParameter('s', $startOfMonth)->setParameter('e', $endOfMonth)
            ->getQuery()->getSingleScalarResult() ?? 0);

        return $this->json([
            'totalRevenue'      => $totalRevenue,
            'monthRevenue'      => $monthRevenue,
            'yearRevenue'       => $yearRevenue,
            'totalExpenses'     => $totalExpenses,
            'monthExpenses'     => $monthExpenses,
            'monthNetProfit'    => $monthRevenue - $monthExpenses,
            'totalNetProfit'    => $totalRevenue - $totalExpenses,
            'monthReservations' => $monthReservations,
        ]);
    }

    #[Route('/recent-reservations', name: 'recent_reservations', methods: ['GET'])]
    public function getRecentReservations(Request $request): JsonResponse
    {
        $bureauId = (int) $request->query->get('bureauId', 0);
        $limit = min(100, max(1, (int) $request->query->get('limit', 10)));
        $qb = $this->reservationRepo->createQueryBuilder('r')->orderBy('r.creeAu', 'DESC')->setMaxResults($limit);
        if ($bureauId) {
            $qb->join('r.voiture', 'bv')->where('bv.bureau = :bid')->setParameter('bid', $bureauId);
        }
        $reservations = $qb->getQuery()->getResult();

        $data = array_map(function ($r) {
            $total     = (float) $r->getTotal();
            $dateDebut = $r->getDateDebut();
            $dateFin   = $r->getDateFin();
            $days = ($dateDebut && $dateFin) ? (int) $dateDebut->diff($dateFin)->days : 0;
            return [
                'id'                => $r->getId(),
                'client'            => ['id' => $r->getClient()?->getId(), 'nom' => $r->getClient()?->getNom()],
                'voiture'           => [
                    'id'     => $r->getVoiture()?->getId(),
                    'marque' => $r->getVoiture()?->getMarque(),
                    'modele' => $r->getVoiture()?->getModele(),
                    'image'  => $r->getVoiture()?->getImagePath(),
                ],
                'dateDebut'         => $dateDebut?->format('Y-m-d H:i:s'),
                'dateFin'           => $dateFin?->format('Y-m-d H:i:s'),
                'nbJours'           => $days,
                'prixJour'          => (float) $r->getVoiture()?->getPrixJour(),
                'total'             => $total,
                'montantPaye'       => (float) $r->getMontantPaye(),
                'montantRestant'    => $total - (float) $r->getMontantPaye(),
                'reservationStatus' => $r->getReservationStatus(),
                'creeAu'            => $r->getCreeAu()?->format('Y-m-d H:i:s'),
            ];
        }, $reservations);

        return $this->json(['data' => $data, 'count' => count($data)]);
    }

    #[Route('/voitures-by-fuel', name: 'voitures_by_fuel', methods: ['GET'])]
    public function getVoituresByFuel(Request $request): JsonResponse
    {
        $bureauId = (int) $request->query->get('bureauId', 0);
        $qb = $this->voitureRepo->createQueryBuilder('v')
            ->select('v.typeCarburant, COUNT(v.id) as count')
            ->groupBy('v.typeCarburant');
        if ($bureauId) {
            $qb->where('v.bureau = :bid')->setParameter('bid', $bureauId);
        }

        $fuelBreakdown = [];
        foreach ($qb->getQuery()->getResult() as $r) {
            $fuelBreakdown[$r['typeCarburant']] = (int) $r['count'];
        }

        return $this->json(['voituresByFuel' => $fuelBreakdown]);
    }

    #[Route('/oil-reminders', name: 'oil_reminders', methods: ['GET'])]
    public function getOilReminders(Request $request): JsonResponse
    {
        $bureauId = (int) $request->query->get('bureauId', 0);
        return $this->json($this->oilChangeService->getDashboardSummary($bureauId));
    }

    /**
     * Returns net profit and pending amount for the top header. Kept in sync with
     * ProfitabilityService::getVehicleProfitability(): revenue is cash actually collected
     * (montantPaye), but expenses use the full committed amount (montant), not just what's
     * been paid — an unpaid expense still reduces profitability, it just hasn't hit cash yet.
     */
    #[Route('/profit-summary', name: 'profit_summary', methods: ['GET'])]
    public function profitSummary(Request $request): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId() ?: ((int) $request->query->get('bureauId', 0)) ?: null;

        $cancelledStatuses = ['annulee', 'annule', 'cancelled'];

        $qb = $this->reservationRepo->createQueryBuilder('r')
            ->select('r.reservationStatus', 'r.total', 'r.montantPaye')
            ->where('r.deletedAt IS NULL');
        if ($bureauId) {
            $qb->join('r.voiture', 'v')->andWhere('v.bureau = :bid')->setParameter('bid', $bureauId);
        }
        $rows = $qb->getQuery()->getScalarResult();

        $revenue       = 0.0;
        $pendingAmount = 0.0;
        foreach ($rows as $row) {
            $status = strtolower($row['reservationStatus'] ?? '');
            $total  = (float) ($row['total'] ?? 0);
            $paid   = (float) ($row['montantPaye'] ?? 0);

            if (!in_array($status, $cancelledStatuses, true)) {
                $revenue       += $paid;
                $pendingAmount += max(0.0, $total - $paid);
            }
        }

        $expQb = $this->depenseRepo->createQueryBuilder('d')
            ->select('SUM(d.montant) as total')
            ->leftJoin('d.voiture', 'v')
            ->where('d.deletedAt IS NULL')
            ->andWhere('v.id IS NULL OR v.deletedAt IS NULL');
        if ($bureauId) {
            $expQb->andWhere('d.bureau = :bid OR v.bureau = :bid')->setParameter('bid', $bureauId);
        }
        $expenses = (float) ($expQb->getQuery()->getSingleScalarResult() ?? 0);

        // Credit installments actually paid — mirrors ProfitabilityService's credit handling.
        $creditQb = $this->em->createQueryBuilder()
            ->select('SUM(i.amountPaid) as total')
            ->from(VehicleCreditInstallment::class, 'i')
            ->join('i.vehicleCredit', 'vc')
            ->join('vc.voiture', 'v')
            ->where("i.status IN ('paid', 'partial')")
            ->andWhere('i.paidAt IS NOT NULL')
            ->andWhere('v.deletedAt IS NULL');
        if ($bureauId) {
            $creditQb->andWhere('v.bureau = :bid')->setParameter('bid', $bureauId);
        }
        $creditPaid = (float) ($creditQb->getQuery()->getSingleScalarResult() ?? 0);

        return $this->json([
            'netProfit'     => round($revenue - $expenses - $creditPaid, 2),
            'pendingAmount' => round($pendingAmount, 2),
        ]);
    }
}
