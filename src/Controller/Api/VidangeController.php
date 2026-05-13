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
            'date'               => $v->getDate()?->format('Y-m-d'),
            'kilometrageSuivant' => $v->getKilometrageSuivant(),
            'filtreAir'          => $v->isFiltreAir(),
            'filtreHuile'        => $v->isFiltreHuile(),
            'filtreCarburant'    => $v->isFiltreCarburant(),
            'prixTotal'          => $v->getPrixTotal(),
            'prixPayee'          => $v->getPrixPayee(),
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
            'date'               => $vidange->getDate()?->format('Y-m-d'),
            'kilometrageSuivant' => $vidange->getKilometrageSuivant(),
            'filtreAir'          => $vidange->isFiltreAir(),
            'filtreHuile'        => $vidange->isFiltreHuile(),
            'filtreCarburant'    => $vidange->isFiltreCarburant(),
            'prixTotal'          => $vidange->getPrixTotal(),
            'prixPayee'          => $vidange->getPrixPayee(),
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
        $vidange->setDate(new \DateTime($data['date']));
        $vidange->setKilometrageSuivant($data['kilometrageSuivant']);
        $vidange->setFiltreAir($data['filtreAir'] ?? false);
        $vidange->setFiltreHuile($data['filtreHuile'] ?? false);
        $vidange->setFiltreCarburant($data['filtreCarburant'] ?? false);
        $vidange->setPrixTotal($data['prixTotal']);
        $vidange->setPrixPayee($data['prixPayee'] ?? 0);
        $vidange->setCreeAu(new \DateTime());
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

        if (isset($data['date']))                $vidange->setDate(new \DateTime($data['date']));
        if (isset($data['kilometrageSuivant']))   $vidange->setKilometrageSuivant($data['kilometrageSuivant']);
        if (isset($data['filtreAir']))            $vidange->setFiltreAir($data['filtreAir']);
        if (isset($data['filtreHuile']))          $vidange->setFiltreHuile($data['filtreHuile']);
        if (isset($data['filtreCarburant']))      $vidange->setFiltreCarburant($data['filtreCarburant']);
        if (isset($data['prixTotal']))            $vidange->setPrixTotal($data['prixTotal']);
        if (isset($data['prixPayee']))            $vidange->setPrixPayee($data['prixPayee']);
        $vidange->setEditAu(new \DateTime());

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