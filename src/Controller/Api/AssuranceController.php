<?php

namespace App\Controller\Api;

use App\Entity\Assurance;
use App\Repository\AssuranceRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/assurance', name: 'app_api_assurance_')]
class AssuranceController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(AssuranceRepository $repo): JsonResponse
    {
        $data = array_map(fn($a) => [
            'id'           => $a->getId(),
            'prix'         => $a->getPrix(),
            'datePaiement' => $a->getDatePaiement()?->format('Y-m-d'),
            'statut'       => $a->getStatut(),
            'numeroMoi'    => $a->getNumeroMoi(),
            'depense'      => $a->getDepense()?->getId(),
            'creePar'      => $a->getCreePar()?->getId(),
            'creeAu'       => $a->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Assurance $assurance): JsonResponse
    {
        return $this->json([
            'id'           => $assurance->getId(),
            'prix'         => $assurance->getPrix(),
            'datePaiement' => $assurance->getDatePaiement()?->format('Y-m-d'),
            'statut'       => $assurance->getStatut(),
            'numeroMoi'    => $assurance->getNumeroMoi(),
            'depense'      => $assurance->getDepense()?->getId(),
            'creePar'      => $assurance->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $assurance = new Assurance();
        $assurance->setPrix($data['prix']);
        $assurance->setDatePaiement(new \DateTime($data['datePaiement']));
        $assurance->setStatut($data['statut'] ?? 'actif');
        $assurance->setNumeroMoi($data['numeroMoi']);
        $assurance->setCreeAu(new \DateTime());
        $assurance->setCreePar($this->getUser());

        if (isset($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
            if ($depense) $assurance->setDepense($depense);
        }

        $em->persist($assurance);
        $em->flush();

        return $this->json(['message' => 'Assurance créée', 'id' => $assurance->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Assurance $assurance, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['prix']))         $assurance->setPrix($data['prix']);
        if (isset($data['datePaiement'])) $assurance->setDatePaiement(new \DateTime($data['datePaiement']));
        if (isset($data['statut']))       $assurance->setStatut($data['statut']);
        if (isset($data['numeroMoi']))    $assurance->setNumeroMoi($data['numeroMoi']);
        $assurance->setEditAu(new \DateTime());

        $em->flush();

        return $this->json(['message' => 'Assurance mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Assurance $assurance, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($assurance);
        $em->flush();

        return $this->json(['message' => 'Assurance supprimée'], 204);
    }
}