<?php

namespace App\Controller\Api;

use App\Entity\SuiviTechnique;
use App\Repository\SuiviTechniqueRepository;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/suivi-technique', name: 'app_api_suivi_technique_')]
class SuiviTechniqueController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(SuiviTechniqueRepository $repo): JsonResponse
    {
        $data = array_map(fn($s) => [
            'id'            => $s->getId(),
            'dateReglages'  => $s->getDateReglages()?->format('Y-m-d'),
            'dateFin'       => $s->getDateFin()?->format('Y-m-d'),
            'voiture'       => $s->getVoiture()?->getId(),
            'creePar'       => $s->getCreePar()?->getId(),
            'creeAu'        => $s->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(SuiviTechnique $suivi): JsonResponse
    {
        return $this->json([
            'id'           => $suivi->getId(),
            'dateReglages' => $suivi->getDateReglages()?->format('Y-m-d'),
            'dateFin'      => $suivi->getDateFin()?->format('Y-m-d'),
            'voiture'      => $suivi->getVoiture()?->getId(),
            'creePar'      => $suivi->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $suivi = new SuiviTechnique();
        $suivi->setDateReglages(new \DateTimeImmutable($data['dateReglages']));
        $suivi->setDateFin(new \DateTimeImmutable($data['dateFin']));
        $suivi->setCreeAu(new \DateTimeImmutable());
        $suivi->setCreePar($this->getUser());

        if (isset($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
            if ($voiture) $suivi->setVoiture($voiture);
        }

        $em->persist($suivi);
        $em->flush();

        return $this->json(['message' => 'Suivi technique créé', 'id' => $suivi->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(SuiviTechnique $suivi, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['dateReglages'])) $suivi->setDateReglages(new \DateTimeImmutable($data['dateReglages']));
        if (isset($data['dateFin']))      $suivi->setDateFin(new \DateTimeImmutable($data['dateFin']));
        $suivi->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Suivi technique mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(SuiviTechnique $suivi, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($suivi);
        $em->flush();

        return $this->json(['message' => 'Suivi technique supprimé'], 204);
    }
}