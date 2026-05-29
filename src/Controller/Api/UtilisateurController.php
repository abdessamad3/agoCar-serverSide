<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\BureauRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;   

#[Route('/api/utilisateur', name: 'app_api_utilisateur_')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
public function list(Request $request, UtilisateurRepository $repo): JsonResponse
{
    // Get query parameters
    $page = (int) $request->query->get('page', 1);
    $limit = (int) $request->query->get('limit', 10);
    $search = $request->query->get('search', '');

    // Build query
    $qb = $repo->createQueryBuilder('u');

    // Search by email
    if (!empty($search)) {
        $qb->andWhere('u.email LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    // Count total
    $qbCount = clone $qb;
    $total = (int) $qbCount->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

    // Paginate
    $offset = ($page - 1) * $limit;
    $utilisateurs = $qb
        ->orderBy('u.id', 'DESC')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();

    $data = array_map(fn($u) => [
        'id'     => $u->getId(),
        'email'  => $u->getEmail(),
        'nom'    => $u->getNom(),
        'prenom' => $u->getPrenom(),
        'roles'  => $u->getRoles(),
        'bureau' => $u->getBureau()?->getId(),
        'creeAu' => $u->getCreeAu()?->format('Y-m-d H:i:s'),
    ], $utilisateurs);

    return $this->json([
        'data' => $data,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Utilisateur $utilisateur): JsonResponse
    {
        return $this->json([
            'id'     => $utilisateur->getId(),
            'email'  => $utilisateur->getEmail(),
            'nom'    => $utilisateur->getNom(),
            'prenom' => $utilisateur->getPrenom(),
            'roles'  => $utilisateur->getRoles(),
            'bureau' => $utilisateur->getBureau()?->getId(),
            'creeAu' => $utilisateur->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu' => $utilisateur->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
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

    // Only ADMIN can modify bureau
    if (isset($data['bureauId'])) {
        if (!$isAdmin) {
            return $this->json(['error' => 'Only admins can modify bureau'], 403);
        }

        $bureau = $bureauRepo->find($data['bureauId']);
        if ($bureau) {
            $utilisateur->setBureau($bureau);
        } else {
            return $this->json(['error' => 'Bureau not found'], 404);
        }
    }

    // Other users can update their own email
    if (isset($data['email'])) {
        $utilisateur->setEmail($data['email']);
    }

    // Only ADMIN can modify roles
    if (isset($data['roles'])) {
        if (!$isAdmin) {
            return $this->json(['error' => 'Only admins can modify roles'], 403);
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