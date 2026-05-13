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
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(AccessoireRepository $repo): JsonResponse
    {
        $data = array_map(fn($a) => [
            'id'           => $a->getId(),
            'nom'          => $a->getNom(),
            'prix'         => $a->getPrix(),
            'typePaiement' => $a->getTypePaiement(),
            'creePar'      => $a->getCreePar()?->getId(),
            'creeAu'       => $a->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Accessoire $accessoire): JsonResponse
    {
        return $this->json([
            'id'           => $accessoire->getId(),
            'nom'          => $accessoire->getNom(),
            'prix'         => $accessoire->getPrix(),
            'typePaiement' => $accessoire->getTypePaiement(),
            'creePar'      => $accessoire->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $accessoire = new Accessoire();
        $accessoire->setNom($data['nom']);
        $accessoire->setPrix($data['prix']);
        $accessoire->setTypePaiement($data['typePaiement'] ?? null);
        $accessoire->setCreeAu(new \DateTime());
        $accessoire->setCreePar($this->getUser());

        $em->persist($accessoire);
        $em->flush();

        return $this->json(['message' => 'Accessoire créé', 'id' => $accessoire->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Accessoire $accessoire, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom']))          $accessoire->setNom($data['nom']);
        if (isset($data['prix']))         $accessoire->setPrix($data['prix']);
        if (isset($data['typePaiement'])) $accessoire->setTypePaiement($data['typePaiement']);
        $accessoire->setEditAu(new \DateTime());

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