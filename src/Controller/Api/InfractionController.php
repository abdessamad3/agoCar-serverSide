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
    private function serialize(Infraction $i): array
    {
        $res  = $i->getReservation();
        $voit = $res?->getVoiture();
        return [
            'id'               => $i->getId(),
            'numeroInfraction' => $i->getNumeroInfraction(),
            'type'             => $i->getType(),
            'description'      => $i->getType(),
            'dateSaisie'       => $i->getDateSaisie()?->format('Y-m-d'),
            'date'             => $i->getDateSaisie()?->format('Y-m-d'),
            'prix'             => $i->getPrix(),
            'montant'          => $i->getPrix(),
            'statut'           => $i->getStatut(),
            'datePaiement'     => $i->getDatePaiement()?->format('Y-m-d'),
            'reservationId'    => $res?->getId(),
            'voitureId'        => $voit?->getId(),
            'voiture'          => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'creeAu'           => $i->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(InfractionRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($i) => $this->serialize($i), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Infraction $infraction): JsonResponse
    {
        return $this->json($this->serialize($infraction));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $infraction = new Infraction();
        $infraction->setNumeroInfraction($data['numeroInfraction'] ?? rand(1000, 9999));
        $infraction->setType($data['type'] ?? $data['description'] ?? '');
        $infraction->setDateSaisie(new \DateTimeImmutable($data['dateSaisie'] ?? $data['date'] ?? 'now'));
        $infraction->setPrix($data['prix'] ?? $data['montant'] ?? '0');
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

        if (isset($data['numeroInfraction']))          $infraction->setNumeroInfraction($data['numeroInfraction']);
        if (isset($data['type']) || isset($data['description']))
            $infraction->setType($data['type'] ?? $data['description']);
        if (isset($data['dateSaisie']) || isset($data['date']))
            $infraction->setDateSaisie(new \DateTimeImmutable($data['dateSaisie'] ?? $data['date']));
        if (isset($data['prix']) || isset($data['montant']))
            $infraction->setPrix($data['prix'] ?? $data['montant']);
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