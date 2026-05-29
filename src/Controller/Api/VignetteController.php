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
    private function serialize(Vignette $v): array
    {
        $dep  = $v->getDepense();
        $voit = $dep?->getVoiture();
        return [
            'id'           => $v->getId(),
            'annee'        => $v->getAnnee(),
            'dateLimite'   => $v->getDateLimite()?->format('Y-m-d'),
            'datePaiement' => $v->getDateLimite()?->format('Y-m-d'),
            'depenseId'    => $dep?->getId(),
            'voitureId'    => $voit?->getId(),
            'voiture'      => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'montant'      => $dep?->getMontant(),
            'creeAu'       => $v->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(VignetteRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($v) => $this->serialize($v), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Vignette $vignette): JsonResponse
    {
        return $this->json($this->serialize($vignette));
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
        $vignette->setDateLimite(new \DateTimeImmutable($data['dateLimite']));
        $vignette->setCreeAu(new \DateTimeImmutable());
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

        if (isset($data['annee']))      $vignette->setAnnee($data['annee']);
        if (isset($data['dateLimite'])) $vignette->setDateLimite(new \DateTimeImmutable($data['dateLimite']));
        $vignette->setEditAu(new \DateTimeImmutable());

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