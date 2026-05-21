<?php

namespace App\Controller\Api;

use App\Entity\Adblue;
use App\Repository\AdblueRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/adblue', name: 'app_api_adblue_')]
class AdblueController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(AdblueRepository $repo): JsonResponse
    {
        $data = array_map(fn($a) => [
            'id'             => $a->getId(),
            'quantiteLitre'  => $a->getQuantiteLitre(),
            'depense'        => $a->getDepense()?->getId(),
            'creePar'        => $a->getCreePar()?->getId(),
            'creeAu'         => $a->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Adblue $adblue): JsonResponse
    {
        return $this->json([
            'id'            => $adblue->getId(),
            'quantiteLitre' => $adblue->getQuantiteLitre(),
            'depense'       => $adblue->getDepense()?->getId(),
            'creePar'       => $adblue->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $adblue = new Adblue();
        $adblue->setQuantiteLitre($data['quantiteLitre']);
        $adblue->setCreeAu(new \DateTimeImmutable());
        $adblue->setCreePar($this->getUser());

        if (isset($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
            if ($depense) $adblue->setDepense($depense);
        }

        $em->persist($adblue);
        $em->flush();

        return $this->json(['message' => 'AdBlue créé', 'id' => $adblue->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Adblue $adblue, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['quantiteLitre'])) $adblue->setQuantiteLitre($data['quantiteLitre']);
        $adblue->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'AdBlue mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Adblue $adblue, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($adblue);
        $em->flush();

        return $this->json(['message' => 'AdBlue supprimé'], 204);
    }
}