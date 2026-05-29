<?php

namespace App\Controller\Api;

use App\Entity\HistoriquePaiement;
use App\Repository\HistoriquePaiementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/historique-paiement', name: 'app_api_historique_paiement_')]
class HistoriquePaiementController extends AbstractController
{
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
    public function list(HistoriquePaiementRepository $repo): JsonResponse
    {
        $items = $repo->findBy([], ['datePaiement' => 'DESC', 'id' => 'DESC']);
        return $this->json(array_map(fn($p) => $this->serialize($p), $items));
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

        // Update reservation.montantPaye
        $newPaid = (float) $reservation->getMontantPaye() + $montant;
        $total   = (float) $reservation->getTotal();
        $reservation->setMontantPaye((string) min($newPaid, $total));

        $em->persist($hp);
        $em->flush();

        return $this->json($this->serialize($hp), 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(HistoriquePaiement $hp, EntityManagerInterface $em): JsonResponse
    {
        $reservation = $hp->getReservation();
        if ($reservation) {
            $newPaid = max(0, (float) $reservation->getMontantPaye() - (float) $hp->getMontant());
            $reservation->setMontantPaye((string) $newPaid);
        }

        $em->remove($hp);
        $em->flush();

        return $this->json(['message' => 'Paiement supprimé'], 204);
    }
}
