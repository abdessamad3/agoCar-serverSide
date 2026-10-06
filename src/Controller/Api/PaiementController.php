<?php

namespace App\Controller\Api;

use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Enum\StatusEnum;
use App\Repository\PaiementRepository;
use App\Repository\CreditRepository;
use App\Repository\ReservationRepository;
use App\Service\ActivityLogService;
use App\Service\PaymentSumHelper;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/paiement', name: 'app_api_paiement_')]
#[IsGranted('ROLE_USER')]
class PaiementController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(private ActivityLogService $activityLog) {}

    /** Bureau-locked staff/managers may only touch payments belonging to their own
     *  bureau (via the reservation's or credit's vehicle). True admins are unrestricted. */
    private function assertBureauAccess(Paiement $paiement): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        $voiture = $paiement->getReservation()?->getVoiture() ?? $paiement->getCredit()?->getVoiture();
        if ($voiture?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Paiement introuvable');
        }
    }

    private function snapshot(Paiement $p): array
    {
        return [
            'montant'      => $p->getMontant(),
            'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
            'statut'       => $p->getStatut()?->value,
            'modePaiement' => $p->getModePaiement(),
            'note'         => $p->getNote(),
            'reservationId'=> $p->getReservation()?->getId(),
            'creditId'     => $p->getCredit()?->getId(),
        ];
    }

    private function serialize(Paiement $p): array
    {
        $res = $p->getReservation();

        return [
            'id'           => $p->getId(),
            'montant'      => $p->getMontant(),
            'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
            'statut'       => $p->getStatut()?->value,
            'modePaiement' => $p->getModePaiement(),
            'note'         => $p->getNote(),
            'reservationId'=> $res?->getId(),
            'creditId'     => $p->getCredit()?->getId(),
            'creePar'      => $p->getCreePar()?->getId(),
            'creeAu'       => $p->getCreeAu()?->format('Y-m-d H:i:s'),
            'reservation'  => $res ? [
                'id'          => $res->getId(),
                'total'       => $res->getTotal(),
                'montantPaye' => $res->getMontantPaye(),
                'client'      => ['id' => $res->getClient()?->getId(), 'nom' => $res->getClient()?->getNom()],
                'voiture'     => ['id' => $res->getVoiture()?->getId(), 'marque' => $res->getVoiture()?->getMarque(), 'modele' => $res->getVoiture()?->getModele()],
                'dateDebut'   => $res->getDateDebut()?->format('Y-m-d'),
                'dateFin'     => $res->getDateFin()?->format('Y-m-d'),
            ] : null,
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId      = $this->getEffectiveBureauId();
        $page          = $this->getPageParam($request);
        $reservationId = (int) $request->query->get('reservationId', 0);
        $creditId      = (int) $request->query->get('creditId', 0);

        $qb = $em->createQueryBuilder()
            ->select('p')
            ->from(Paiement::class, 'p')
            ->leftJoin('p.reservation', 'r')
            ->leftJoin('r.voiture', 'rv')
            ->leftJoin('p.credit', 'c')
            ->leftJoin('c.voiture', 'cv')
            ->orderBy('p.datePaiement', 'DESC');

        if ($reservationId) {
            $qb->andWhere('p.reservation = :reservationId')->setParameter('reservationId', $reservationId);
        } elseif ($creditId) {
            $qb->andWhere('p.credit = :creditId')->setParameter('creditId', $creditId);
        } elseif ($bureauId !== null) {
            // r.bureau is a legacy field that's never actually populated on reservation
            // creation -- go through the reservation's voiture for the real bureau.
            $qb->andWhere('rv.bureau = :bureauId OR cv.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        [$items, $total] = $this->paginateQb($qb, $page, $reservationId > 0 || $creditId > 0);
        return $this->json(['data' => array_map(fn($p) => $this->serialize($p), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Paiement $paiement): JsonResponse
    {
        $this->assertBureauAccess($paiement);
        return $this->json($this->serialize($paiement));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CreditRepository $creditRepo,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $reservation = null;
        if (!empty($data['reservationId'])) {
            $reservation = $reservationRepo->find($data['reservationId']);
            if (!$reservation) {
                return $this->json(['error' => 'Réservation introuvable'], 404);
            }
            $montant = (float) ($data['montant'] ?? 0);
            if ($montant === 0.0) {
                return $this->json(['error' => 'Le montant ne peut pas être zéro'], 400);
            }
            // Negative montant = refund, recorded as its own row rather than editing/deleting
            // the original payment (preserves the real history for cash/revenue reports).
            // Bounded so you can't refund more than was actually collected.
            if ($montant < 0 && abs($montant) > (float) $reservation->getMontantPaye()) {
                return $this->json(['error' => 'Le remboursement dépasse le montant déjà payé pour cette réservation'], 400);
            }
        }

        $paiement = new Paiement();
        $paiement->setMontant($data['montant']);
        $paiement->setDatePaiement(new \DateTimeImmutable($data['datePaiement'] ?? 'now'));
        $paiement->setStatut(isset($data['statut']) ? StatusEnum::from($data['statut']) : StatusEnum::PAYEE);
        $paiement->setModePaiement($data['modePaiement'] ?? null);
        $paiement->setNote($data['note'] ?? null);
        $paiement->setCreeAu(new \DateTimeImmutable());
        $paiement->setCreePar($this->getUser());

        if ($reservation) {
            $paiement->setReservation($reservation);
        } elseif (!empty($data['creditId'])) {
            $credit = $creditRepo->find($data['creditId']);
            if ($credit) $paiement->setCredit($credit);
        }

        $em->persist($paiement);
        $em->flush();

        if ($reservation) {
            $this->recomputeReservationMontantPaye($reservation, $em);
        }

        $this->activityLog->logCreate('Paiement', $paiement->getId(), $this->snapshot($paiement), $reservation?->getBureau());

        return $this->json($this->serialize($paiement), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Paiement $paiement, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($paiement);
        $data    = json_decode($request->getContent(), true);
        $oldSnap = $this->snapshot($paiement);

        if (isset($data['montant']))      $paiement->setMontant($data['montant']);
        if (isset($data['datePaiement'])) $paiement->setDatePaiement(new \DateTimeImmutable($data['datePaiement']));
        if (isset($data['statut']))       $paiement->setStatut(StatusEnum::from($data['statut']));
        if (array_key_exists('modePaiement', $data)) $paiement->setModePaiement($data['modePaiement']);
        if (array_key_exists('note', $data))         $paiement->setNote($data['note']);
        $paiement->setEditAu(new \DateTimeImmutable());

        $em->flush();

        $reservation = $paiement->getReservation();
        if ($reservation) {
            $this->recomputeReservationMontantPaye($reservation, $em);
        }

        $this->activityLog->logUpdate('Paiement', $paiement->getId(), $oldSnap, $this->snapshot($paiement), $reservation?->getBureau());

        return $this->json($this->serialize($paiement));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Paiement $paiement, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($paiement);
        $oldSnap     = $this->snapshot($paiement);
        $reservation = $paiement->getReservation();
        $id          = $paiement->getId();

        $paiement->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        if ($reservation) {
            $this->recomputeReservationMontantPaye($reservation, $em);
        }

        $this->activityLog->logDelete('Paiement', $id, $oldSnap, $reservation?->getBureau());

        return $this->json(['message' => 'Paiement supprimé'], 200);
    }

    private function recomputeReservationMontantPaye(Reservation $reservation, EntityManagerInterface $em): void
    {
        $total = PaymentSumHelper::sumActivePayments($em, Paiement::class, 'reservation', $reservation);
        $reservation->setMontantPaye((string) $total);
        $em->flush();
    }
}
