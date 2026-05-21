<?php

namespace App\Controller\Api;

use App\Entity\Vidange;
use App\Repository\VidangeRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vidange', name: 'app_api_vidange_')]
class VidangeController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(VidangeRepository $repo): JsonResponse
    {
        $data = array_map(fn($v) => [
            'id'                 => $v->getId(),
            'kilometrageSuivant' => $v->getKilometrageSuivant(),
            'filtreAir'          => $v->isFiltreAir(),
            'filtreHuile'        => $v->isFiltreHuile(),
            'filtreCarburant'    => $v->isFiltreCarburant(),
            'depense'            => $v->getDepense()?->getId(),
            'creePar'            => $v->getCreePar()?->getId(),
            'creeAu'             => $v->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Vidange $vidange): JsonResponse
    {
        return $this->json([
            'id'                 => $vidange->getId(),
            'kilometrageSuivant' => $vidange->getKilometrageSuivant(),
            'filtreAir'          => $vidange->isFiltreAir(),
            'filtreHuile'        => $vidange->isFiltreHuile(),
            'filtreCarburant'    => $vidange->isFiltreCarburant(),
            'depense'            => $vidange->getDepense()?->getId(),
            'creePar'            => $vidange->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $vidange = new Vidange();
        $vidange->setKilometrageSuivant($data['kilometrageSuivant']);
        $vidange->setFiltreAir($data['filtreAir'] ?? false);
        $vidange->setFiltreHuile($data['filtreHuile'] ?? false);
        $vidange->setFiltreCarburant($data['filtreCarburant'] ?? false);
        $vidange->setCreeAu(new \DateTimeImmutable());
        $vidange->setCreePar($this->getUser());

        if (isset($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
            if ($depense) $vidange->setDepense($depense);
        }

        $em->persist($vidange);
        $em->flush();

        return $this->json(['message' => 'Vidange créée', 'id' => $vidange->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Vidange $vidange, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['kilometrageSuivant'])) $vidange->setKilometrageSuivant($data['kilometrageSuivant']);
        if (isset($data['filtreAir']))          $vidange->setFiltreAir($data['filtreAir']);
        if (isset($data['filtreHuile']))        $vidange->setFiltreHuile($data['filtreHuile']);
        if (isset($data['filtreCarburant']))    $vidange->setFiltreCarburant($data['filtreCarburant']);
        $vidange->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Vidange mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Vidange $vidange, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($vidange);
        $em->flush();

        return $this->json(['message' => 'Vidange supprimée'], 204);
    }
}