<?php

namespace App\Controller\Api;

use App\Entity\Bureau;
use App\Repository\BureauRepository;
use App\Repository\CompanyRepository;
use App\Repository\UtilisateurRepository;
use App\Trait\BureauAwareTrait;
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
    use BureauAwareTrait;

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
            $limit = min(200, max(1, (int) $request->query->get('limit', 20)));
            $search = $request->query->get('search', '');
            $statut = $request->query->get('statut', '');
            $sort = $request->query->get('sort', 'id');
            $direction = strtoupper($request->query->get('direction', 'ASC'));

            // Validate pagination params
            $page = max(1, $page);
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
                'id'          => $b->getId(),
                'nom'         => $b->getNom(),
                'adresse'     => $b->getAdresse(),
                'telephone'   => $b->getTelephone(),
                'statut'      => $b->getStatut(),
                'companyId'   => $b->getCompany()?->getId(),
                'companyNom'  => $b->getCompany()?->getNom(),
                'companyLogo' => $b->getCompany()?->getLogo(),
                'managerId'   => $b->getManager()?->getId(),
                'managerNom'  => $b->getManager() ? trim(($b->getManager()->getPrenom() ?? '') . ' ' . ($b->getManager()->getNom() ?? '')) : null,
                'creeAu'      => $b->getCreeAu()?->format('Y-m-d H:i:s'),
                'editAu'      => $b->getEditAu()?->format('Y-m-d H:i:s'),
            ], $bureaus);

            return $this->json([
                'data' => $data,
                'meta' => [
                    'total'       => $total,
                    'page'        => $page,
                    'limit'       => $limit,
                    'totalPages'  => (int) ceil($total / $limit) ?: 1,
                    'hasNextPage' => $page < ((int) ceil($total / $limit) ?: 1),
                    'hasPrevPage' => $page > 1,
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
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId !== null && $bureau->getId() !== $bureauId) {
            throw $this->createNotFoundException('Bureau introuvable');
        }
        try {
            return $this->json([
                'id'         => $bureau->getId(),
                'nom'        => $bureau->getNom(),
                'adresse'    => $bureau->getAdresse(),
                'telephone'  => $bureau->getTelephone(),
                'statut'     => $bureau->getStatut(),
                'companyId'   => $bureau->getCompany()?->getId(),
                'companyNom'  => $bureau->getCompany()?->getNom(),
                'companyLogo' => $bureau->getCompany()?->getLogo(),
                'managerId'  => $bureau->getManager()?->getId(),
                'managerNom' => $bureau->getManager() ? trim(($bureau->getManager()->getPrenom() ?? '') . ' ' . ($bureau->getManager()->getNom() ?? '')) : null,
                'creeAu'     => $bureau->getCreeAu()?->format('Y-m-d H:i:s'),
                'editAu'     => $bureau->getEditAu()?->format('Y-m-d H:i:s'),
                'creePar'    => $bureau->getCreePar()?->getId(),
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
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em, CompanyRepository $companyRepo, UtilisateurRepository $userRepo): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data['nom'])) {
                return $this->json(['error' => 'Validation failed', 'message' => 'nom is required'], 400);
            }
            if (empty($data['statut'])) {
                return $this->json(['error' => 'Validation failed', 'message' => 'statut is required'], 400);
            }

            $bureau = new Bureau();
            $bureau->setNom($data['nom']);
            $bureau->setAdresse($data['adresse'] ?? null);
            $bureau->setTelephone($data['telephone'] ?? null);
            $bureau->setStatut($data['statut']);
            $bureau->setCreeAu(new \DateTimeImmutable());

            if (!empty($data['companyId'])) {
                $company = $companyRepo->find($data['companyId']);
                if ($company) $bureau->setCompany($company);
            }

            if (!empty($data['managerId'])) {
                $manager = $userRepo->find($data['managerId']);
                if ($manager) {
                    $bureau->setManager($manager);
                    // Access is read only from user.bureau now (BureauAwareTrait no
                    // longer falls back to Bureau.manager) -- keep them in sync here
                    // so assigning a manager still grants that bureau's data in one step.
                    $manager->setBureau($bureau);
                }
            }

            $em->persist($bureau);
            $em->flush();

            return $this->json([
                'message' => 'Bureau créé avec succès',
                'id' => $bureau->getId(),
                'data' => [
                    'id'        => $bureau->getId(),
                    'nom'       => $bureau->getNom(),
                    'adresse'   => $bureau->getAdresse(),
                    'telephone' => $bureau->getTelephone(),
                    'statut'    => $bureau->getStatut(),
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
    #[IsGranted('ROLE_ADMIN')]
    public function update(Bureau $bureau, Request $request, EntityManagerInterface $em, CompanyRepository $companyRepo, UtilisateurRepository $userRepo): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['nom']))        $bureau->setNom($data['nom']);
            if (isset($data['adresse']))    $bureau->setAdresse($data['adresse']);
            if (array_key_exists('telephone', $data)) $bureau->setTelephone($data['telephone']);
            if (isset($data['statut']))     $bureau->setStatut($data['statut']);
            if (array_key_exists('companyId', $data)) {
                $bureau->setCompany($data['companyId'] ? $companyRepo->find($data['companyId']) : null);
            }
            if (array_key_exists('managerId', $data)) {
                $manager = $data['managerId'] ? $userRepo->find($data['managerId']) : null;
                $bureau->setManager($manager);
                // Only auto-grant on assignment, same reasoning as create(). Clearing a
                // manager does NOT auto-clear their personal bureau -- they may still be
                // legitimate staff there; that's a separate, explicit decision for an
                // admin to make on the Users page if it's actually meant to revoke access.
                if ($manager) $manager->setBureau($bureau);
            }
            
            $bureau->setEditAu(new \DateTimeImmutable());

            $em->flush();

            return $this->json([
                'message' => 'Bureau mis à jour avec succès',
                'data' => [
                    'id'        => $bureau->getId(),
                    'nom'       => $bureau->getNom(),
                    'adresse'   => $bureau->getAdresse(),
                    'telephone' => $bureau->getTelephone(),
                    'statut'    => $bureau->getStatut(),
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
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, BureauRepository $repo, UtilisateurRepository $utilisateurRepo, EntityManagerInterface $em): JsonResponse
    {
        try {
            $bureau = $repo->find($id);

            if (!$bureau) {
                return $this->json([
                    'error' => 'Not found',
                    'message' => 'Bureau not found'
                ], 404);
            }

            $userCount = $utilisateurRepo->count(['bureau' => $bureau]);
            if ($userCount > 0) {
                return $this->json([
                    'error' => 'Cannot delete',
                    'message' => "Cannot delete this bureau — it has {$userCount} user(s) assigned. Reassign or remove them first."
                ], 409);
            }

            $bureau->setDeletedAt(new \DateTimeImmutable());
            $em->flush();

            return $this->json([
                'message' => 'Bureau supprimé avec succès'
            ], 200);

        } catch (ForeignKeyConstraintViolationException $e) {
            return $this->json([
                'error' => 'Constraint violation',
                'message' => 'Cannot delete this bureau — it has related records.'
            ], 409);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete bureau',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }
}