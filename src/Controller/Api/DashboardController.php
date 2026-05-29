<?php

namespace App\Controller\Api;

use App\Repository\VoitureRepository;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/dashboard', name: 'app_api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private VoitureRepository $voitureRepo,
        private ReservationRepository $reservationRepo,
        private ClientRepository $clientRepo,
        private DepenseRepository $depenseRepo,
        private EntityManagerInterface $em
    ) {}

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $now = new \DateTime();

        $totalVoitures    = $this->voitureRepo->count([]);
        $availableVoitures = $this->voitureRepo->count(['voitureStatus' => 'available']);
        $totalClients     = $this->clientRepo->count([]);

        $activeReservations = (int) $this->reservationRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.dateDebut <= :now')->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult();

        // Revenue = sum of all reservation totals
        $revenue = (float) ($this->reservationRepo->createQueryBuilder('r')
            ->select('SUM(r.total)')
            ->getQuery()->getSingleScalarResult() ?? 0);

        // Expenses = sum of all depenses
        $expenses = (float) ($this->depenseRepo->createQueryBuilder('d')
            ->select('SUM(d.montant)')
            ->getQuery()->getSingleScalarResult() ?? 0);

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
        $startOfMonth = (new \DateTime('first day of this month'))->setTime(0, 0, 0);
        $endOfMonth   = (new \DateTime('last day of this month'))->setTime(23, 59, 59);
        $startOfYear  = new \DateTime('first day of January this year');
        $endOfYear    = new \DateTime('last day of December this year');

        $totalRevenue = (float) ($this->reservationRepo->createQueryBuilder('r')
            ->select('SUM(r.total)')->getQuery()->getSingleScalarResult() ?? 0);

        $monthRevenue = (float) ($this->reservationRepo->createQueryBuilder('r')
            ->select('SUM(r.total)')
            ->where('r.creeAu >= :start')->andWhere('r.creeAu <= :end')
            ->setParameter('start', $startOfMonth)->setParameter('end', $endOfMonth)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $yearRevenue = (float) ($this->reservationRepo->createQueryBuilder('r')
            ->select('SUM(r.total)')
            ->where('r.creeAu >= :start')->andWhere('r.creeAu <= :end')
            ->setParameter('start', $startOfYear)->setParameter('end', $endOfYear)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $totalExpenses = (float) ($this->depenseRepo->createQueryBuilder('d')
            ->select('SUM(d.montant)')->getQuery()->getSingleScalarResult() ?? 0);

        $monthExpenses = (float) ($this->depenseRepo->createQueryBuilder('d')
            ->select('SUM(d.montant)')
            ->where('d.creeAu >= :start')->andWhere('d.creeAu <= :end')
            ->setParameter('start', $startOfMonth)->setParameter('end', $endOfMonth)
            ->getQuery()->getSingleScalarResult() ?? 0);

        $monthReservations = (int) ($this->reservationRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.creeAu >= :start')->andWhere('r.creeAu <= :end')
            ->setParameter('start', $startOfMonth)->setParameter('end', $endOfMonth)
            ->getQuery()->getSingleScalarResult() ?? 0);

        return $this->json([
            'totalRevenue'       => $totalRevenue,
            'monthRevenue'       => $monthRevenue,
            'yearRevenue'        => $yearRevenue,
            'totalExpenses'      => $totalExpenses,
            'monthExpenses'      => $monthExpenses,
            'monthNetProfit'     => $monthRevenue - $monthExpenses,
            'totalNetProfit'     => $totalRevenue - $totalExpenses,
            'monthReservations'  => $monthReservations,
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

        $data = array_map(function ($r) {
            $total = (float) $r->getTotal();
            $dateDebut = $r->getDateDebut();
            $dateFin   = $r->getDateFin();
            $days = ($dateDebut && $dateFin)
                ? (int) $dateDebut->diff($dateFin)->days
                : 0;
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