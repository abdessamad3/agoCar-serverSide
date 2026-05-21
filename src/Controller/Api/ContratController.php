<?php

namespace App\Controller\Api;

use App\Entity\Contrat;
use App\Repository\ContratRepository;
use App\Repository\ClientRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/contrat', name: 'app_api_contrat_')]
class ContratController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ContratRepository $repo): JsonResponse
    {
        $data = array_map(fn($c) => [
            'id'          => $c->getId(),
            'client'      => ['id' => $c->getClient()?->getId(), 'nom' => $c->getClient()?->getNom()],
            'reservation' => $c->getReservation()?->getId(),
            'creePar'     => $c->getCreePar()?->getId(),
            'creeAu'      => $c->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Contrat $contrat): JsonResponse
    {
        return $this->json([
            'id'          => $contrat->getId(),
            'client'      => ['id' => $contrat->getClient()?->getId(), 'nom' => $contrat->getClient()?->getNom()],
            'reservation' => $contrat->getReservation()?->getId(),
            'creePar'     => $contrat->getCreePar()?->getId(),
            'creeAu'      => $contrat->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'      => $contrat->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $contrat = new Contrat();
        $contrat->setCreeAu(new \DateTimeImmutable());
        $contrat->setCreePar($this->getUser());

        if (isset($data['clientId'])) {
            $client = $clientRepo->find($data['clientId']);
            if ($client) $contrat->setClient($client);
        }

        if (isset($data['reservationId'])) {
            $reservation = $reservationRepo->find($data['reservationId']);
            if ($reservation) $contrat->setReservation($reservation);
        }

        $em->persist($contrat);
        $em->flush();

        return $this->json(['message' => 'Contrat créé', 'id' => $contrat->getId()], 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Contrat $contrat, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($contrat);
        $em->flush();

        return $this->json(['message' => 'Contrat supprimé'], 204);
    }
}