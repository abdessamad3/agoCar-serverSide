<?php

namespace App\Controller\Api;

use App\Entity\Reparation;
use App\Repository\ReparationRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reparation', name: 'app_api_reparation_')]
class ReparationController extends AbstractController
{
    private function serialize(Reparation $r): array
    {
        $dep = $r->getDepense();
        $voit = $dep?->getVoiture();
        return [
            'id'                   => $r->getId(),
            'descriptionTechnique' => $r->getDescriptionTechnique(),
            'depenseId'            => $dep?->getId(),
            'voitureId'            => $voit?->getId(),
            'voiture'              => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'montant'              => $dep?->getMontant(),
            'date'                 => $dep?->getDate()?->format('Y-m-d'),
            'dateDebut'            => $r->getDateDebut()?->format('Y-m-d'),
            'dateFin'              => $r->getDateFin()?->format('Y-m-d'),
            'statut'               => $dep?->getStatut()?->value,
            'creeAu'               => $r->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ReparationRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($r) => $this->serialize($r), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Reparation $reparation): JsonResponse
    {
        return $this->json($this->serialize($reparation));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $reparation = new Reparation();
        $reparation->setDescriptionTechnique($data['descriptionTechnique']);
        if (!empty($data['dateDebut'])) $reparation->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (!empty($data['dateFin']))   $reparation->setDateFin(new \DateTimeImmutable($data['dateFin']));
        $reparation->setCreeAu(new \DateTimeImmutable());
        $reparation->setCreePar($this->getUser());

        if (isset($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
            if ($depense) $reparation->setDepense($depense);
        }

        $em->persist($reparation);
        $em->flush();

        return $this->json(['message' => 'Réparation créée', 'id' => $reparation->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Reparation $reparation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['descriptionTechnique'])) $reparation->setDescriptionTechnique($data['descriptionTechnique']);
        if (isset($data['dateDebut'])) $reparation->setDateDebut($data['dateDebut'] ? new \DateTimeImmutable($data['dateDebut']) : null);
        if (isset($data['dateFin']))   $reparation->setDateFin($data['dateFin'] ? new \DateTimeImmutable($data['dateFin']) : null);
        $reparation->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Réparation mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Reparation $reparation, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($reparation);
        $em->flush();

        return $this->json(['message' => 'Réparation supprimée'], 204);
    }
}