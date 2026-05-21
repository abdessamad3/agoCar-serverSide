<?php

namespace App\Controller\Api;

use App\Entity\Infraction;
use App\Repository\InfractionRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/infraction', name: 'app_api_infraction_')]
class InfractionController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(InfractionRepository $repo): JsonResponse
    {
        $data = array_map(fn($i) => [
            'id'               => $i->getId(),
            'numeroInfraction' => $i->getNumeroInfraction(),
            'type'             => $i->getType(),
            'dateSaisie'       => $i->getDateSaisie()?->format('Y-m-d'),
            'prix'             => $i->getPrix(),
            'statut'           => $i->getStatut(),
            'datePaiement'     => $i->getDatePaiement()?->format('Y-m-d'),
            'reservation'      => $i->getReservation()?->getId(),
            'creePar'          => $i->getCreePar()?->getId(),
            'creeAu'           => $i->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Infraction $infraction): JsonResponse
    {
        return $this->json([
            'id'               => $infraction->getId(),
            'numeroInfraction' => $infraction->getNumeroInfraction(),
            'type'             => $infraction->getType(),
            'dateSaisie'       => $infraction->getDateSaisie()?->format('Y-m-d'),
            'prix'             => $infraction->getPrix(),
            'statut'           => $infraction->getStatut(),
            'datePaiement'     => $infraction->getDatePaiement()?->format('Y-m-d'),
            'reservation'      => $infraction->getReservation()?->getId(),
            'creePar'          => $infraction->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $infraction = new Infraction();
        $infraction->setNumeroInfraction($data['numeroInfraction']);
        $infraction->setType($data['type']);
        $infraction->setDateSaisie(new \DateTimeImmutable($data['dateSaisie']));
        $infraction->setPrix($data['prix']);
        $infraction->setStatut($data['statut'] ?? 'en_attente');
        $infraction->setDatePaiement(isset($data['datePaiement']) ? new \DateTimeImmutable($data['datePaiement']) : null);
        $infraction->setCreeAu(new \DateTimeImmutable());
        $infraction->setCreePar($this->getUser());

        if (isset($data['reservationId'])) {
            $reservation = $reservationRepo->find($data['reservationId']);
            if ($reservation) $infraction->setReservation($reservation);
        }

        $em->persist($infraction);
        $em->flush();

        return $this->json(['message' => 'Infraction créée', 'id' => $infraction->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Infraction $infraction, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['numeroInfraction'])) $infraction->setNumeroInfraction($data['numeroInfraction']);
        if (isset($data['type']))             $infraction->setType($data['type']);
        if (isset($data['dateSaisie']))       $infraction->setDateSaisie(new \DateTimeImmutable($data['dateSaisie']));
        if (isset($data['prix']))             $infraction->setPrix($data['prix']);
        if (isset($data['statut']))           $infraction->setStatut($data['statut']);
        if (isset($data['datePaiement']))     $infraction->setDatePaiement(new \DateTimeImmutable($data['datePaiement']));
        $infraction->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Infraction mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Infraction $infraction, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($infraction);
        $em->flush();

        return $this->json(['message' => 'Infraction supprimée'], 204);
    }
}