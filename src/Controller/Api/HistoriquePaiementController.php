<?php

namespace App\Controller\Api;

use App\Entity\HistoriquePaiement;
use App\Repository\HistoriquePaiementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @deprecated Superseded by {@see PaiementController}. No frontend code calls these
 * routes anymore as of the Phase 3 cutover (location-dossier, paiement-client, and
 * paiement-list all moved to /api/paiement). Routes are left intact and fully
 * functional — not disabled — as the rollback path; do not build new features here.
 */
#[Route('/api/historique-paiement', name: 'app_api_historique_paiement_')]
#[IsGranted('ROLE_USER')]
class HistoriquePaiementController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    private function serialize(HistoriquePaiement $p): array
    {
        $res = $p->getReservation();
        return [
            'id'           => $p->getId(),
            'montant'      => $p->getMontant(),
            'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
            'modePaiement' => $p->getModePaiement(),
            'note'         => $p->getNote(),
            'creeAu'       => $p->getCreeAu()?->format('Y-m-d H:i:s'),
            'reservation'  => $res ? [
                'id'       => $res->getId(),
                'total'    => $res->getTotal(),
                'montantPaye' => $res->getMontantPaye(),
                'client'   => ['id' => $res->getClient()?->getId(), 'nom' => $res->getClient()?->getNom()],
                'voiture'  => ['id' => $res->getVoiture()?->getId(), 'marque' => $res->getVoiture()?->getMarque(), 'modele' => $res->getVoiture()?->getModele()],
                'dateDebut'=> $res->getDateDebut()?->format('Y-m-d'),
                'dateFin'  => $res->getDateFin()?->format('Y-m-d'),
            ] : null,
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('hp')
            ->from(HistoriquePaiement::class, 'hp')
            ->join('hp.reservation', 'r')
            ->join('r.voiture', 'v')
            ->where('hp.deletedAt IS NULL')
            ->orderBy('hp.datePaiement', 'DESC')
            ->addOrderBy('hp.id', 'DESC');

        if ($bureauId !== null) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        [$items, $total] = $this->paginateQb($qb, $page);
        return $this->json(['data' => array_map(fn($p) => $this->serialize($p), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $reservation = $reservationRepo->find($data['reservationId'] ?? 0);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }

        $montant = (float) ($data['montant'] ?? 0);
        if ($montant <= 0) {
            return $this->json(['error' => 'Le montant doit être supérieur à 0'], 400);
        }

        $hp = new HistoriquePaiement();
        $hp->setReservation($reservation);
        $hp->setMontant((string) $montant);
        $hp->setDatePaiement(new \DateTimeImmutable($data['datePaiement'] ?? 'now'));
        $hp->setModePaiement($data['modePaiement'] ?? null);
        $hp->setNote($data['note'] ?? null);
        $hp->setCreeAu(new \DateTimeImmutable());

        $em->persist($hp);
        $em->flush();

        // Recompute montantPaye as exact sum of all active payments
        $this->recomputeMontantPaye($reservation, $em);

        return $this->json($this->serialize($hp), 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(HistoriquePaiement $hp, EntityManagerInterface $em): JsonResponse
    {
        $reservation = $hp->getReservation();
        $hp->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        if ($reservation) {
            $this->recomputeMontantPaye($reservation, $em);
        }

        return $this->json(['message' => 'Paiement supprimé'], 200);
    }

    private function recomputeMontantPaye(\App\Entity\Reservation $reservation, EntityManagerInterface $em): void
    {
        $sum = $em->createQueryBuilder()
            ->select('SUM(hp.montant)')
            ->from(HistoriquePaiement::class, 'hp')
            ->where('hp.reservation = :res')
            ->andWhere('hp.deletedAt IS NULL')
            ->setParameter('res', $reservation)
            ->getQuery()
            ->getSingleScalarResult();

        $reservation->setMontantPaye((string) max(0.0, (float) ($sum ?? 0)));
        $em->flush();
    }
}
