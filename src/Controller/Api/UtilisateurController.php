<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\BureauRepository;
use App\Repository\CompanyRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/utilisateur', name: 'app_api_utilisateur_')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
public function list(Request $request, UtilisateurRepository $repo, CompanyRepository $companyRepo): JsonResponse
{
    // Get query parameters
    $page   = max(1, (int) $request->query->get('page', 1));
    $limit  = min(200, max(1, (int) $request->query->get('limit', 20)));
    $search = $request->query->get('search', '');

    // Build query with LEFT JOIN to avoid proxy EntityNotFoundException
    $qb = $repo->createQueryBuilder('u')
        ->leftJoin('u.bureau', 'b')
        ->addSelect('b');

    // Search by email
    if (!empty($search)) {
        $qb->andWhere('u.email LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    // Count total
    $qbCount = $repo->createQueryBuilder('u');
    if (!empty($search)) {
        $qbCount->andWhere('u.email LIKE :search')->setParameter('search', '%' . $search . '%');
    }
    $total = (int) $qbCount->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

    // Paginate
    $offset = ($page - 1) * $limit;
    $utilisateurs = $qb
        ->orderBy('u.id', 'DESC')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();

    // Build managerId → company map
    $managerCompanyMap = [];
    foreach ($companyRepo->findAll() as $c) {
        if ($c->getManager()) {
            $managerCompanyMap[$c->getManager()->getId()] = ['id' => $c->getId(), 'nom' => $c->getNom()];
        }
    }

    $data = array_map(function ($u) use ($managerCompanyMap) {
        $company = $managerCompanyMap[$u->getId()] ?? null;
        return [
            'id'         => $u->getId(),
            'email'      => $u->getEmail(),
            'nom'        => $u->getNom(),
            'prenom'     => $u->getPrenom(),
            'telephone'  => $u->getTelephone(),
            'photo'      => $u->getPhoto(),
            'roles'      => $u->getRoles(),
            'actif'      => $u->isActif(),
            'bureau'     => $u->getBureau()?->getId(),
            'bureauNom'  => $u->getBureau()?->getNom(),
            'companyId'  => $company['id'] ?? null,
            'companyNom' => $company['nom'] ?? null,
            'creeAu'     => $u->getCreeAu()?->format('Y-m-d H:i:s'),
        ];
    }, $utilisateurs);

    return $this->json([
        'data' => $data,
        'meta' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'totalPages'  => max(1, (int) ceil($total / $limit)),
            'hasNextPage' => $page < max(1, (int) ceil($total / $limit)),
            'hasPrevPage' => $page > 1,
        ]
    ]);
}
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        BureauRepository $bureauRepo,
        ValidatorInterface $validator
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        $allowedRoles = ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_STAFF', 'ROLE_ACCOUNTANT', 'ROLE_MECHANIC'];
        $data['roles'] = array_values(array_filter($data['roles'] ?? [], fn($r) => $r !== 'ROLE_USER'));
        if (empty($data['roles']) || !is_array($data['roles'])) {
            return $this->json(['error' => 'roles is required'], 400);
        }
        $invalidRoles = array_diff($data['roles'], $allowedRoles);
        if (!empty($invalidRoles)) {
            return $this->json(['error' => 'Invalid roles: ' . implode(', ', $invalidRoles)], 400);
        }

        $existing = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'Email already in use'], 409);
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($data['email']);
        $utilisateur->setPassword($hasher->hashPassword($utilisateur, $data['password']));
        $utilisateur->setNom($data['nom'] ?? '');
        $utilisateur->setPrenom($data['prenom'] ?? '');
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setRoles($data['roles']);
        $utilisateur->setActif($data['actif'] ?? true);
        $utilisateur->setCreeAu(new \DateTimeImmutable());

        if (!empty($data['bureauId'])) {
            $bureau = $bureauRepo->find($data['bureauId']);
            if ($bureau) {
                $utilisateur->setBureau($bureau);
            }
        }

        $errors = $validator->validate($utilisateur);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $em->persist($utilisateur);
        $em->flush();

        return $this->json(['message' => 'Utilisateur créé', 'id' => $utilisateur->getId()], 201);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Utilisateur $utilisateur): JsonResponse
    {
        return $this->json([
            'id'             => $utilisateur->getId(),
            'email'          => $utilisateur->getEmail(),
            'nom'            => $utilisateur->getNom(),
            'prenom'         => $utilisateur->getPrenom(),
            'telephone'      => $utilisateur->getTelephone(),
            'photo'          => $utilisateur->getPhoto(),
            'roles'          => $utilisateur->getRoles(),
            'actif'          => $utilisateur->isActif(),
            'bureau'         => $utilisateur->getBureau()?->getId(),
            'bureauNom'      => $utilisateur->getBureau()?->getNom(),
            'hasSignature'   => $utilisateur->getSignatureBlob() !== null,
            'signatureBlob'  => $utilisateur->getSignatureBlob(),
            'creeAu'         => $utilisateur->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'         => $utilisateur->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}/signature', name: 'signature_save', methods: ['PUT'])]
    public function saveSignature(Utilisateur $utilisateur, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());
        if (!$isAdmin && $currentUser->getId() !== $utilisateur->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $blob = $data['signatureBlob'] ?? null;

        if ($blob !== null && !str_starts_with($blob, 'data:image/')) {
            return $this->json(['error' => 'Invalid signature format'], 400);
        }

        $utilisateur->setSignatureBlob($blob);
        $utilisateur->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Signature saved', 'hasSignature' => $blob !== null]);
    }

    #[Route('/{id}/signature', name: 'signature_delete', methods: ['DELETE'])]
    public function deleteSignature(Utilisateur $utilisateur, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());
        if (!$isAdmin && $currentUser->getId() !== $utilisateur->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $utilisateur->setSignatureBlob(null);
        $utilisateur->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Signature removed']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
public function update(
    Utilisateur $utilisateur,
    Request $request,
    EntityManagerInterface $em,
    UserPasswordHasherInterface $hasher,
    BureauRepository $bureauRepo,
    CompanyRepository $companyRepo,
    ValidatorInterface $validator
): JsonResponse {
    $data = json_decode($request->getContent(), true);
    $currentUser = $this->getUser();
    $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());

    // Only ADMIN can modify bureau
    if (array_key_exists('bureauId', $data)) {
        if (!$isAdmin) {
            return $this->json(['error' => 'Only admins can modify bureau'], 403);
        }

        if ($data['bureauId'] === null || $data['bureauId'] === '' || $data['bureauId'] === 0) {
            $utilisateur->setBureau(null);
        } else {
            $bureau = $bureauRepo->find($data['bureauId']);
            if (!$bureau) {
                return $this->json(['error' => 'Bureau not found'], 404);
            }
            $utilisateur->setBureau($bureau);
        }
    }

    // Other users can update their own email
    if (isset($data['email'])) {
        $utilisateur->setEmail($data['email']);
    }

    // Users can update their own name
    if (array_key_exists('nom', $data) && trim((string) $data['nom']) !== '') {
        $utilisateur->setNom($data['nom']);
    }
    if (array_key_exists('prenom', $data) && trim((string) $data['prenom']) !== '') {
        $utilisateur->setPrenom($data['prenom']);
    }

    // Only ADMIN can modify roles
    if (isset($data['roles'])) {
        if (!$isAdmin) {
            return $this->json(['error' => 'Only admins can modify roles'], 403);
        }
        $allowedRoles = ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_STAFF', 'ROLE_ACCOUNTANT', 'ROLE_MECHANIC'];
        $data['roles'] = array_values(array_filter($data['roles'], fn($r) => $r !== 'ROLE_USER'));
        if (empty($data['roles'])) {
            return $this->json(['error' => 'roles is required'], 400);
        }
        $invalidRoles = array_diff($data['roles'], $allowedRoles);
        if (!empty($invalidRoles)) {
            return $this->json(['error' => 'Invalid roles: ' . implode(', ', $invalidRoles)], 400);
        }
        $utilisateur->setRoles($data['roles']);
    }

    // Only allow password change if current user is admin or updating their own password
    if (isset($data['password'])) {
        if ($isAdmin || $currentUser->getId() === $utilisateur->getId()) {
            $utilisateur->setPassword($hasher->hashPassword($utilisateur, $data['password']));
        } else {
            return $this->json(['error' => 'You are not allowed to change this password'], 403);
        }
    }

    if (isset($data['actif'])) {
        $utilisateur->setActif((bool) $data['actif']);
    }

    if (array_key_exists('telephone', $data)) {
        $utilisateur->setTelephone($data['telephone'] ?: null);
    }

    if (array_key_exists('photo', $data)) {
        $utilisateur->setPhoto($data['photo'] ?: null);
    }

    if (array_key_exists('companyId', $data)) {
        // Remove this user as manager from any previous company
        foreach ($companyRepo->findBy(['manager' => $utilisateur]) as $prev) {
            $prev->setManager(null);
        }
        if (!empty($data['companyId'])) {
            $company = $companyRepo->find($data['companyId']);
            if ($company) {
                $company->setManager($utilisateur);
            }
        }
    }

    $utilisateur->setEditAu(new \DateTimeImmutable());
    
    $errors = $validator->validate($utilisateur);
    if (count($errors) > 0) {
        return $this->json(['errors' => (string) $errors], 400);
    }

    $em->flush();

    return $this->json(['message' => 'Utilisateur mis à jour']);
}

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Utilisateur $utilisateur, 
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo // Needed to check for reservations
    ): JsonResponse {
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());

        // SECURITY CHECK: Only Admins can delete users
        if (!$isAdmin) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // SAFETY CHECK: Don't delete yourself!
        if ($currentUser->getId() === $utilisateur->getId()) {
            return $this->json(['error' => 'You cannot delete your own account'], 400);
        }

        try {
            $em->remove($utilisateur);
            $em->flush();
            return $this->json(['message' => 'Utilisateur supprimé'], 204);
            
        } catch (ForeignKeyConstraintViolationException $e) {
            return $this->json([
                'message' => 'Cannot delete user. They likely have active reservations or history.'
            ], 409);
        }
    }
}