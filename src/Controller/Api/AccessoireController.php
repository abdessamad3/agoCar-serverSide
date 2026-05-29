<?php

namespace App\Controller\Api;

use App\Entity\Accessoire;
use App\Repository\AccessoireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/accessoire', name: 'app_api_accessoire_')]
class AccessoireController extends AbstractController
{
    private function serialize(Accessoire $a): array
    {
        return [
            'id'          => $a->getId(),
            'nom'         => $a->getNom(),
            'prix'        => $a->getPrix(),
            'prixJour'    => $a->getPrix(),
            'description' => $a->getDescription(),
            'creeAu'      => $a->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(AccessoireRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($a) => $this->serialize($a), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Accessoire $accessoire): JsonResponse
    {
        return $this->json($this->serialize($accessoire));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $accessoire = new Accessoire();
        $accessoire->setNom($data['nom']);
        $accessoire->setPrix($data['prixJour'] ?? $data['prix'] ?? '0');
        if (isset($data['description'])) $accessoire->setDescription($data['description']);
        $accessoire->setCreeAu(new \DateTimeImmutable());

        $em->persist($accessoire);
        $em->flush();

        return $this->json(['message' => 'Accessoire créé', 'id' => $accessoire->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Accessoire $accessoire, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom']))                          $accessoire->setNom($data['nom']);
        if (isset($data['prixJour']) || isset($data['prix'])) $accessoire->setPrix($data['prixJour'] ?? $data['prix']);
        if (isset($data['description']))                  $accessoire->setDescription($data['description']);

        $em->flush();

        return $this->json(['message' => 'Accessoire mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Accessoire $accessoire, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($accessoire);
        $em->flush();

        return $this->json(['message' => 'Accessoire supprimé'], 204);
    }
}