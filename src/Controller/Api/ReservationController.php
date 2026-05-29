<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reservation', name: 'app_api_reservation_')]
class ReservationController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ReservationRepository $repo): JsonResponse
    {
        $reservations = $repo->findAll();
        $data = array_map(fn($r) => [
            'id'               => $r->getId(),
            'client'           => ['id' => $r->getClient()?->getId(), 'nom' => $r->getClient()?->getNom()],
            'voiture'          => ['id' => $r->getVoiture()?->getId(), 'marque' => $r->getVoiture()?->getMarque(), 'modele' => $r->getVoiture()?->getModele()],
            'dateDebut'        => $r->getDateDebut()?->format('Y-m-d'),
            'dateFin'          => $r->getDateFin()?->format('Y-m-d'),
            'total'            => $r->getTotal(),
            'montantPaye'      => $r->getMontantPaye(),
            'modePaiement'     => $r->getModePaiement(),
            'reservationStatus'=> $r->getReservationStatus(),
            'creeAu'           => $r->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $reservations);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Reservation $reservation): JsonResponse
    {
        $total     = (float) $reservation->getTotal();
        $dateDebut = $reservation->getDateDebut();
        $dateFin   = $reservation->getDateFin();
        $days = ($dateDebut && $dateFin) ? (int) $dateDebut->diff($dateFin)->days : 0;

        return $this->json([
            'id'                => $reservation->getId(),
            'client'            => [
                'id'              => $reservation->getClient()?->getId(),
                'nom'             => $reservation->getClient()?->getNom(),
                'telephone'       => $reservation->getClient()?->getTelephone(),
                'cin'             => $reservation->getClient()?->getCin(),
                'permisConduite'  => $reservation->getClient()?->getPermisConduite(),
            ],
            'voiture'           => [
                'id'     => $reservation->getVoiture()?->getId(),
                'marque' => $reservation->getVoiture()?->getMarque(),
                'modele' => $reservation->getVoiture()?->getModele(),
                'annee'  => $reservation->getVoiture()?->getAnnee(),
                'image'  => $reservation->getVoiture()?->getImagePath(),
            ],
            'dateDebut'         => $dateDebut?->format('Y-m-d H:i:s'),
            'dateFin'           => $dateFin?->format('Y-m-d H:i:s'),
            'nbJours'           => $days,
            'prixJour'          => (float) $reservation->getVoiture()?->getPrixJour(),
            'total'             => $total,
            'montantPaye'       => (float) $reservation->getMontantPaye(),
            'montantRestant'    => $total - (float) $reservation->getMontantPaye(),
            'modePaiement'      => $reservation->getModePaiement(),
            'reservationStatus' => $reservation->getReservationStatus(),
            'accessoires'       => $reservation->getAccessoires()->map(fn($a) => ['id' => $a->getId(), 'nom' => $a->getNom()])->toArray(),
            'creeAu'            => $reservation->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'            => $reservation->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $reservation = new Reservation();

        $client = $clientRepo->find($data['clientId']);
        $voiture = $voitureRepo->find($data['voitureId']);

        if (!$client || !$voiture) {
            return $this->json(['error' => 'Client ou voiture introuvable'], 404);
        }

        $reservation->setClient($client);
        $reservation->setVoiture($voiture);
        $reservation->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        $reservation->setDateFin(new \DateTimeImmutable($data['dateFin']));
        $reservation->setTotal($data['total']);
        $reservation->setReservationStatus($data['reservationStatus'] ?? 'confirmed');
        $reservation->setMontantPaye((string) ($data['montantPaye'] ?? 0));
        $reservation->setModePaiement($data['modePaiement'] ?? null);
        $reservation->setCreeAu(new \DateTimeImmutable());

        $voiture->setVoitureStatus('rented');
        $voiture->setReservationStatus('confirmed');

        $em->persist($reservation);
        $em->flush();

        return $this->json(['message' => 'Réservation créée', 'id' => $reservation->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['clientId'])) {
            $client = $clientRepo->find($data['clientId']);
            if ($client) $reservation->setClient($client);
        }
        if (isset($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
            if ($voiture) $reservation->setVoiture($voiture);
        }
        if (isset($data['dateDebut']))         $reservation->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (isset($data['dateFin']))           $reservation->setDateFin(new \DateTimeImmutable($data['dateFin']));
        if (isset($data['total']))             $reservation->setTotal($data['total']);
        if (isset($data['reservationStatus'])) $reservation->setReservationStatus($data['reservationStatus']);
        if (isset($data['montantPaye']))        $reservation->setMontantPaye((string) $data['montantPaye']);
        if (isset($data['modePaiement']))       $reservation->setModePaiement($data['modePaiement']);
        $reservation->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Réservation mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Reservation $reservation, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($reservation);
        $em->flush();

        return $this->json(['message' => 'Réservation supprimée'], 204);
    }
}