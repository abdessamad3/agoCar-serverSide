<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\BureauRepository;
use App\Repository\UtilisateurRepository;
use App\Trait\BureauAwareTrait;
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
    use BureauAwareTrait;

    /** Bureau-locked staff/managers may only see/touch users in their own bureau.
     *  Bureau-less users (bureau IS NULL) stay visible/reachable to a legitimately
     *  bureau-assigned viewer -- they're company-wide/unassigned accounts, not
     *  another bureau's private roster. True admins (getEffectiveBureauId() ===
     *  null) are unrestricted. A misconfigured viewer (sentinel 0 -- bureau-less
     *  non-admin) gets NO exception for this: they see nothing at all, same as
     *  everywhere else in the app, not even other bureau-less accounts. */
    private function assertBureauAccess(Utilisateur $utilisateur): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;
        if ($bureauId !== 0 && $utilisateur->getBureau() === null) return;

        if ($utilisateur->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
public function list(Request $request, UtilisateurRepository $repo): JsonResponse
{
    // Get query parameters
    $page   = max(1, (int) $request->query->get('page', 1));
    $limit  = min(200, max(1, (int) $request->query->get('limit', 20)));
    $search = $request->query->get('search', '');
    $bureauId = $this->getEffectiveBureauId();

    // Build query with LEFT JOIN to avoid proxy EntityNotFoundException
    $qb = $repo->createQueryBuilder('u')
        ->leftJoin('u.bureau', 'b')
        ->addSelect('b');

    // Search by email
    if (!empty($search)) {
        $qb->andWhere('u.email LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }
    if ($bureauId === 0) {
        // Misconfigured viewer (bureau-less non-admin): sees nothing, not even
        // other bureau-less accounts.
        $qb->andWhere('1 = 0');
    } elseif ($bureauId !== null) {
        // Bureau-less users (bureau IS NULL) stay visible to a legitimately
        // bureau-assigned viewer -- they're company-wide/unassigned accounts, not
        // another bureau's private roster, and SQL's "=" never matches NULL so
        // they'd otherwise vanish the moment their bureau is cleared.
        $qb->andWhere('u.bureau = :bureauId OR u.bureau IS NULL')->setParameter('bureauId', $bureauId);
    }

    // Count total
    $qbCount = $repo->createQueryBuilder('u');
    if (!empty($search)) {
        $qbCount->andWhere('u.email LIKE :search')->setParameter('search', '%' . $search . '%');
    }
    if ($bureauId === 0) {
        $qbCount->andWhere('1 = 0');
    } elseif ($bureauId !== null) {
        $qbCount->andWhere('u.bureau = :bureauId OR u.bureau IS NULL')->setParameter('bureauId', $bureauId);
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

    $data = array_map(function ($u) {
        // Company follows the user's bureau, not a single per-company "manager"
        // slot -- any number of people on the same bureau share its company.
        $company = $u->getBureau()?->getCompany();
        $company = $company ? ['id' => $company->getId(), 'nom' => $company->getNom()] : null;
        return [
            'id'         => $u->getId(),
            'email'      => $u->getEmail(),
            'nom'        => $u->getNom(),
            'prenom'     => $u->getPrenom(),
            'telephone'  => $u->getTelephone(),
            'photo'      => $u->getPhoto(),
            'roles'      => $u->getRoles(),
            'actif'      => $u->isActif(),
            'langue'     => $u->getLangue(),
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
        if (!empty($data['langue'])) {
            $utilisateur->setLangue($data['langue']);
        }
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
        $this->assertBureauAccess($utilisateur);
        return $this->json([
            'id'             => $utilisateur->getId(),
            'email'          => $utilisateur->getEmail(),
            'nom'            => $utilisateur->getNom(),
            'prenom'         => $utilisateur->getPrenom(),
            'telephone'      => $utilisateur->getTelephone(),
            'photo'          => $utilisateur->getPhoto(),
            'roles'          => $utilisateur->getRoles(),
            'actif'          => $utilisateur->isActif(),
            'langue'         => $utilisateur->getLangue(),
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
    ValidatorInterface $validator
): JsonResponse {
    $data = json_decode($request->getContent(), true);
    $currentUser = $this->getUser();
    $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());
    $isSelf  = $currentUser->getId() === $utilisateur->getId();

    // Only admin or the account owner can modify profile fields at all
    if (!$isAdmin && !$isSelf) {
        return $this->json(['error' => 'Forbidden'], 403);
    }

    // Only ADMIN can modify bureau
    if (array_key_exists('bureauId', $data)) {
        if (!$isAdmin) {
            return $this->json(['error' => 'Only admins can modify bureau'], 403);
        }

        if ($data['bureauId'] === null || $data['bureauId'] === '' || $data['bureauId'] === 0) {
            // Clearing bureau is only safe for a true admin -- for anyone else it
            // silently locks them out of the whole app (they see nothing, by design),
            // which looks like a bug rather than an intentional account change.
            if (!in_array('ROLE_ADMIN', $utilisateur->getRoles(), true)) {
                return $this->json([
                    'error' => 'Impossible de retirer le bureau d\'un compte non-admin — cela le bloquerait entièrement. Assignez-lui d\'abord un autre bureau, ou passez-le en admin.'
                ], 422);
            }
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

    // Any user can set their own UI/email language preference.
    if (isset($data['langue'])) {
        $utilisateur->setLangue($data['langue']);
    }

    if (array_key_exists('telephone', $data)) {
        $utilisateur->setTelephone($data['telephone'] ?: null);
    }

    if (array_key_exists('photo', $data)) {
        $utilisateur->setPhoto($data['photo'] ?: null);
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