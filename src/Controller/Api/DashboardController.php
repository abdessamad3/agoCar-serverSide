<?php

namespace App\Controller\Api;

use App\Repository\VoitureRepository;
use App\Repository\ReservationRepository;
use App\Repository\PaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard', name: 'app_api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private VoitureRepository $voitureRepo,
        private ReservationRepository $reservationRepo,
        private PaiementRepository $paiementRepo,
        private EntityManagerInterface $em
    ) {}

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        // 1. Total Voitures
        $totalVoitures = $this->voitureRepo->count([]);

        // 2. Available Voitures (voitureStatus = 'available')
        $availableVoitures = $this->voitureRepo->count(['voitureStatus' => 'available']);

        // 3. Active Reservations (happening NOW)
        $qbActive = $this->reservationRepo->createQueryBuilder('r');
        $now = new \DateTime();
        $activeReservations = (int) $qbActive
            ->select('COUNT(r.id)')
            ->where('r.dateDebut <= :now')
            ->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        // 4. Total Revenue (Sum of all paiements)
        $qbRevenue = $this->paiementRepo->createQueryBuilder('p');
        $revenue = $qbRevenue
            ->select('SUM(p.montant)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return $this->json([
            'totalVoitures' => $totalVoitures,
            'availableVoitures' => $availableVoitures,
            'activeReservations' => $activeReservations,
            'revenue' => (float) $revenue
        ]);
    }

    #[Route('/voitures-status', name: 'voitures_status', methods: ['GET'])]
    public function getVoituresStatus(): JsonResponse
    {
        // Breakdown of voitures by status
        $qb = $this->voitureRepo->createQueryBuilder('v');
        $qb->select('v.voitureStatus, COUNT(v.id) as count')
           ->groupBy('v.voitureStatus');

        $results = $qb->getQuery()->getResult();

        $statusBreakdown = [];
        foreach ($results as $result) {
            $statusBreakdown[$result['voitureStatus']] = (int) $result['count'];
        }

        return $this->json([
            'voituresByStatus' => $statusBreakdown
        ]);
    }

    #[Route('/reservations-status', name: 'reservations_status', methods: ['GET'])]
    public function getReservationsStatus(): JsonResponse
    {
        // Count of reservations by status
        $qb = $this->reservationRepo->createQueryBuilder('r');
        
        $now = new \DateTime();

        // Active (currently happening)
        $active = (int) $qb
            ->select('COUNT(r.id)')
            ->where('r.dateDebut <= :now')
            ->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        // Upcoming (in the future)
        $qbUpcoming = $this->reservationRepo->createQueryBuilder('r');
        $upcoming = (int) $qbUpcoming
            ->select('COUNT(r.id)')
            ->where('r.dateDebut > :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        // Past (completed)
        $qbPast = $this->reservationRepo->createQueryBuilder('r');
        $past = (int) $qbPast
            ->select('COUNT(r.id)')
            ->where('r.dateFin < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'active' => $active,
            'upcoming' => $upcoming,
            'past' => $past,
            'total' => $active + $upcoming + $past
        ]);
    }

   #[Route('/revenue-summary', name: 'revenue_summary', methods: ['GET'])]
public function getRevenueSummary(): JsonResponse
{
    // Total revenue
    $qbTotal = $this->paiementRepo->createQueryBuilder('p');
    $totalRevenue = (float) ($qbTotal
        ->select('SUM(p.montant)')
        ->getQuery()
        ->getSingleScalarResult() ?? 0);

    // Revenue this month
    $now = new \DateTime();
    $startOfMonth = (new \DateTime())->modify('first day of this month');
    $endOfMonth = (new \DateTime())->modify('last day of this month');

    $qbMonth = $this->paiementRepo->createQueryBuilder('p');
    $monthRevenue = (float) ($qbMonth
        ->select('SUM(p.montant)')
        ->where('p.datePaiement >= :startMonth')
        ->andWhere('p.datePaiement <= :endMonth')
        ->setParameter('startMonth', $startOfMonth)
        ->setParameter('endMonth', $endOfMonth)
        ->getQuery()
        ->getSingleScalarResult() ?? 0);

    // Revenue this year
    $startOfYear = (new \DateTime('first day of January'));
    $endOfYear = (new \DateTime('last day of December'));

    $qbYear = $this->paiementRepo->createQueryBuilder('p');
    $yearRevenue = (float) ($qbYear
        ->select('SUM(p.montant)')
        ->where('p.datePaiement >= :startYear')
        ->andWhere('p.datePaiement <= :endYear')
        ->setParameter('startYear', $startOfYear)
        ->setParameter('endYear', $endOfYear)
        ->getQuery()
        ->getSingleScalarResult() ?? 0);

    return $this->json([
        'totalRevenue' => $totalRevenue,
        'monthRevenue' => $monthRevenue,
        'yearRevenue' => $yearRevenue
    ]);
}
    #[Route('/recent-reservations', name: 'recent_reservations', methods: ['GET'])]
    public function getRecentReservations(): JsonResponse
    {
        // Last 10 reservations
        $qb = $this->reservationRepo->createQueryBuilder('r');
        $reservations = $qb
            ->orderBy('r.creeAu', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'client' => $r->getClient()?->getNom(),
            'voiture' => $r->getVoiture()?->getMarque() . ' ' . $r->getVoiture()?->getModele(),
            'dateDebut' => $r->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $r->getDateFin()?->format('Y-m-d'),
            'total' => $r->getTotal(),
            'montantPaye' => $r->getMontantPaye(),
            'creeAu' => $r->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $reservations);

        return $this->json([
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/voitures-by-fuel', name: 'voitures_by_fuel', methods: ['GET'])]
    public function getVoituresByFuel(): JsonResponse
    {
        // Voitures grouped by fuel type
        $qb = $this->voitureRepo->createQueryBuilder('v');
        $qb->select('v.typeCarburant, COUNT(v.id) as count')
           ->groupBy('v.typeCarburant');

        $results = $qb->getQuery()->getResult();

        $fuelBreakdown = [];
        foreach ($results as $result) {
            $fuelBreakdown[$result['typeCarburant']] = (int) $result['count'];
        }

        return $this->json([
            'voituresByFuel' => $fuelBreakdown
        ]);
    }
}