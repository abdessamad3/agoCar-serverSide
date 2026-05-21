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
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ReparationRepository $repo): JsonResponse
    {
        $data = array_map(fn($r) => [
            'id'                    => $r->getId(),
            'descriptionTechnique'  => $r->getDescriptionTechnique(),
            'depense'               => $r->getDepense()?->getId(),
            'creePar'               => $r->getCreePar()?->getId(),
            'creeAu'                => $r->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Reparation $reparation): JsonResponse
    {
        return $this->json([
            'id'                   => $reparation->getId(),
            'descriptionTechnique' => $reparation->getDescriptionTechnique(),
            'depense'              => $reparation->getDepense()?->getId(),
            'creePar'              => $reparation->getCreePar()?->getId(),
        ]);
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