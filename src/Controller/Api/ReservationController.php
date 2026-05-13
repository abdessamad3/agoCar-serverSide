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
final class ReservationController extends AbstractController
{
     #[Route('', name: 'list', methods: ['GET'])]
    public function list(ReservationRepository $repo): JsonResponse
    {
        $reservations = $repo->findAll();
        $data = array_map(fn($r) => [
            'id'             => $r->getId(),
            'client'         => ['id' => $r->getClient()?->getId(), 'nom' => $r->getClient()?->getNom()],
            'voiture'        => ['id' => $r->getVoiture()?->getId(), 'marque' => $r->getVoiture()?->getMarque(), 'modele' => $r->getVoiture()?->getModele()],
            'dateDebut'      => $r->getDateDebut()?->format('Y-m-d'),
            'dateFin'        => $r->getDateFin()?->format('Y-m-d'),
            'total'          => $r->getTotal(),
            'montantPaye'    => $r->getMontantPaye(),
            'montantRestant' => $r->getMontantRestant(),
            'lavage'         => $r->isLavage(),
            'creeAu'         => $r->getCreeAu()?->format('Y-m-d H:i:s'),
            'creePar'        => $r->getCreePar()?->getId(),
        ], $reservations);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Reservation $reservation): JsonResponse
    {
        return $this->json([
            'id'             => $reservation->getId(),
            'client'         => ['id' => $reservation->getClient()?->getId(), 'nom' => $reservation->getClient()?->getNom()],
            'voiture'        => ['id' => $reservation->getVoiture()?->getId(), 'marque' => $reservation->getVoiture()?->getMarque()],
            'dateDebut'      => $reservation->getDateDebut()?->format('Y-m-d'),
            'dateFin'        => $reservation->getDateFin()?->format('Y-m-d'),
            'total'          => $reservation->getTotal(),
            'montantPaye'    => $reservation->getMontantPaye(),
            'montantRestant' => $reservation->getMontantRestant(),
            'lavage'         => $reservation->isLavage(),
            'description'    => $reservation->getDescription(),
            'creeAu'         => $reservation->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'         => $reservation->getEditAu()?->format('Y-m-d H:i:s'),
            'creePar'        => $reservation->getCreePar()?->getId(),
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
        $reservation->setDateDebut(new \DateTime($data['dateDebut']));
        $reservation->setDateFin(new \DateTime($data['dateFin']));
        $reservation->setTotal($data['total']);
        $reservation->setMontantPaye($data['montantPaye'] ?? 0);
        $reservation->setMontantRestant($data['montantRestant'] ?? $data['total']);
        $reservation->setLavage($data['lavage'] ?? false);
        $reservation->setDescription($data['description'] ?? null);
        $reservation->setCreeAu(new \DateTime());
        $reservation->setCreePar($this->getUser());

        // update voiture status
        $voiture->setVoitureStatus('rented');
        $voiture->setReservationStatus('confirmed');

        $em->persist($reservation);
        $em->flush();

        return $this->json(['message' => 'Réservation créée', 'id' => $reservation->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Reservation $reservation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['dateDebut']))      $reservation->setDateDebut(new \DateTime($data['dateDebut']));
        if (isset($data['dateFin']))        $reservation->setDateFin(new \DateTime($data['dateFin']));
        if (isset($data['total']))          $reservation->setTotal($data['total']);
        if (isset($data['montantPaye']))    $reservation->setMontantPaye($data['montantPaye']);
        if (isset($data['montantRestant'])) $reservation->setMontantRestant($data['montantRestant']);
        if (isset($data['lavage']))         $reservation->setLavage($data['lavage']);
        if (isset($data['description']))    $reservation->setDescription($data['description']);
        $reservation->setEditAu(new \DateTime());

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
