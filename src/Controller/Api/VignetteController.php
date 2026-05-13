<?php

namespace App\Controller\Api;

use App\Entity\Vignette;
use App\Repository\VignetteRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vignette', name: 'app_api_vignette_')]
class VignetteController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(VignetteRepository $repo): JsonResponse
    {
        $data = array_map(fn($v) => [
            'id'           => $v->getId(),
            'annee'        => $v->getAnnee(),
            'prix'         => $v->getPrix(),
            'datePaiement' => $v->getDatePaiement()?->format('Y-m-d'),
            'dateLimite'   => $v->getDateLimite()?->format('Y-m-d'),
            'depense'      => $v->getDepense()?->getId(),
            'creePar'      => $v->getCreePar()?->getId(),
            'creeAu'       => $v->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Vignette $vignette): JsonResponse
    {
        return $this->json([
            'id'           => $vignette->getId(),
            'annee'        => $vignette->getAnnee(),
            'prix'         => $vignette->getPrix(),
            'datePaiement' => $vignette->getDatePaiement()?->format('Y-m-d'),
            'dateLimite'   => $vignette->getDateLimite()?->format('Y-m-d'),
            'depense'      => $vignette->getDepense()?->getId(),
            'creePar'      => $vignette->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $vignette = new Vignette();
        $vignette->setAnnee($data['annee']);
        $vignette->setPrix($data['prix']);
        $vignette->setDatePaiement(new \DateTime($data['datePaiement']));
        $vignette->setDateLimite(new \DateTime($data['dateLimite']));
        $vignette->setCreeAu(new \DateTime());
        $vignette->setCreePar($this->getUser());

        if (isset($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
            if ($depense) $vignette->setDepense($depense);
        }

        $em->persist($vignette);
        $em->flush();

        return $this->json(['message' => 'Vignette créée', 'id' => $vignette->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Vignette $vignette, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['annee']))        $vignette->setAnnee($data['annee']);
        if (isset($data['prix']))         $vignette->setPrix($data['prix']);
        if (isset($data['datePaiement'])) $vignette->setDatePaiement(new \DateTime($data['datePaiement']));
        if (isset($data['dateLimite']))   $vignette->setDateLimite(new \DateTime($data['dateLimite']));
        $vignette->setEditAu(new \DateTime());

        $em->flush();

        return $this->json(['message' => 'Vignette mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Vignette $vignette, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($vignette);
        $em->flush();

        return $this->json(['message' => 'Vignette supprimée'], 204);
    }
}