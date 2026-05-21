<?php

namespace App\Controller\Api;

use App\Entity\Bureau;
use App\Repository\BureauRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/bureau', name: 'app_api_bureau_')]
class BureauController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(BureauRepository $repo): JsonResponse
    {
        $bureaux = $repo->findAll();
        $data = array_map(fn($b) => [
            'id'      => $b->getId(),
            'nom'     => $b->getNom(),
            'statut'  => $b->getStatut(),
            'creeAu'  => $b->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $bureaux);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Bureau $bureau): JsonResponse
    {
        return $this->json([
            'id'     => $bureau->getId(),
            'nom'    => $bureau->getNom(),
            'statut' => $bureau->getStatut(),
            'creeAu' => $bureau->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu' => $bureau->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $bureau = new Bureau();
        $bureau->setNom($data['nom']);
        $bureau->setStatut($data['statut'] ?? 'actif');
        $bureau->setCreeAu(new \DateTimeImmutable());
        $bureau->setCreePar($this->getUser());

        $em->persist($bureau);
        $em->flush();

        return $this->json(['message' => 'Bureau créé', 'id' => $bureau->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Bureau $bureau, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom']))    $bureau->setNom($data['nom']);
        if (isset($data['statut'])) $bureau->setStatut($data['statut']);
        $bureau->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Bureau mis à jour']);
    }

  #[Route('/{id}', name: 'app_api_bureau_delete', methods: ['DELETE'])]
public function delete(int $id, BureauRepository $repo, EntityManagerInterface $em): JsonResponse
{
    $bureau = $repo->find($id);

    if (!$bureau) {
        return $this->json(['message' => 'Bureau not found'], 404);
    }

    try {
        // Attempt to delete
        $em->remove($bureau);
        $em->flush();

        return $this->json(['message' => 'Bureau deleted successfully'], 204);

    } catch (ForeignKeyConstraintViolationException $e) {
        // CATCH THE ERROR HERE!
        return $this->json([
            'message' => 'Cannot delete this bureau. It currently has cars assigned to it.'
        ], 409); // 409 = "Conflict" (The perfect status code for this)
    }
}
}