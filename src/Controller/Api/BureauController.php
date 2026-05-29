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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;

#[Route('/api/bureau', name: 'app_api_bureau_')]
#[IsGranted('ROLE_USER')]
class BureauController extends AbstractController
{
    public function __construct(private KernelInterface $kernel) {}

    /**
     * List all bureaus with pagination, search, and filters
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, BureauRepository $repo): JsonResponse
    {
        try {
            // Get query parameters
            $page = (int) $request->query->get('page', 1);
            $limit = (int) $request->query->get('limit', 10);
            $search = $request->query->get('search', '');
            $statut = $request->query->get('statut', '');
            $sort = $request->query->get('sort', 'id');
            $direction = strtoupper($request->query->get('direction', 'ASC'));

            // Validate pagination params
            $page = max(1, $page);
            $limit = min(100, max(1, $limit));
            $direction = in_array($direction, ['ASC', 'DESC']) ? $direction : 'ASC';

            // Build filters
            $filters = [
                'search' => $search,
                'statut' => $statut,
                'sort' => $sort,
                'direction' => $direction,
            ];

            // Get bureaus
            $bureaus = $repo->findWithFilters($filters, $page, $limit);
            $total = $repo->countWithFilters($filters);
            $pages = ceil($total / $limit);

            // Format response
            $data = array_map(fn($b) => [
                'id'      => $b->getId(),
                'nom'     => $b->getNom(),
                'adresse' => $b->getAdresse(),
                'statut'  => $b->getStatut(),
                'creeAu'  => $b->getCreeAu()?->format('Y-m-d H:i:s'),
                'editAu'  => $b->getEditAu()?->format('Y-m-d H:i:s'),
            ], $bureaus);

            return $this->json([
                'data' => $data,
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int) $pages,
                    'hasMore' => $page < $pages,
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch bureaus',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    /**
     * Get single bureau by ID
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Bureau $bureau): JsonResponse
    {
        try {
            return $this->json([
                'id'      => $bureau->getId(),
                'nom'     => $bureau->getNom(),
                'adresse' => $bureau->getAdresse(),
                'statut'  => $bureau->getStatut(),
                'creeAu'  => $bureau->getCreeAu()?->format('Y-m-d H:i:s'),
                'editAu'  => $bureau->getEditAu()?->format('Y-m-d H:i:s'),
                'creePar' => $bureau->getCreePar()?->getId(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch bureau',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    /**
     * Create new bureau
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (empty($data['nom'])) {
                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'nom is required'
                ], 400);
            }

            if (empty($data['statut'])) {
                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'statut is required'
                ], 400);
            }

            $bureau = new Bureau();
            $bureau->setNom($data['nom']);
            $bureau->setAdresse($data['adresse'] ?? null);
            $bureau->setStatut($data['statut']);
            $bureau->setCreeAu(new \DateTimeImmutable());
            $bureau->setCreePar($this->getUser());

            $em->persist($bureau);
            $em->flush();

            return $this->json([
                'message' => 'Bureau créé avec succès',
                'id' => $bureau->getId(),
                'data' => [
                    'id' => $bureau->getId(),
                    'nom' => $bureau->getNom(),
                    'adresse' => $bureau->getAdresse(),
                    'statut' => $bureau->getStatut(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create bureau',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    /**
     * Update existing bureau
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Bureau $bureau, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['nom']))     $bureau->setNom($data['nom']);
            if (isset($data['adresse'])) $bureau->setAdresse($data['adresse']);
            if (isset($data['statut']))  $bureau->setStatut($data['statut']);
            
            $bureau->setEditAu(new \DateTimeImmutable());

            $em->flush();

            return $this->json([
                'message' => 'Bureau mis à jour avec succès',
                'data' => [
                    'id' => $bureau->getId(),
                    'nom' => $bureau->getNom(),
                    'adresse' => $bureau->getAdresse(),
                    'statut' => $bureau->getStatut(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to update bureau',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    /**
     * Delete bureau
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, BureauRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        try {
            $bureau = $repo->find($id);

            if (!$bureau) {
                return $this->json([
                    'error' => 'Not found',
                    'message' => 'Bureau not found'
                ], 404);
            }

            // Attempt to delete
            $em->remove($bureau);
            $em->flush();

            return $this->json([
                'message' => 'Bureau supprimé avec succès'
            ], 200);

        } catch (ForeignKeyConstraintViolationException $e) {
            // Bureau has cars assigned to it
            return $this->json([
                'error' => 'Constraint violation',
                'message' => 'Cannot delete this bureau. It has cars assigned to it.'
            ], 409);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete bureau',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }
}