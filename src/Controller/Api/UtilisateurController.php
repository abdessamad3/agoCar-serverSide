<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/utilisateur', name: 'app_api_utilisateur_')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(UtilisateurRepository $repo): JsonResponse
    {
        $data = array_map(fn($u) => [
            'id'      => $u->getId(),
            'email'   => $u->getEmail(),
            'roles'   => $u->getRoles(),
            'bureau'  => $u->getBureau()?->getId(),
            'creePar' => $u->getCreePar()?->getId(),
            'creeAu'  => $u->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Utilisateur $utilisateur): JsonResponse
    {
        return $this->json([
            'id'      => $utilisateur->getId(),
            'email'   => $utilisateur->getEmail(),
            'roles'   => $utilisateur->getRoles(),
            'bureau'  => $utilisateur->getBureau()?->getId(),
            'creePar' => $utilisateur->getCreePar()?->getId(),
            'creeAu'  => $utilisateur->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'  => $utilisateur->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        Utilisateur $utilisateur,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email']))    $utilisateur->setEmail($data['email']);
        if (isset($data['roles']))    $utilisateur->setRoles($data['roles']);
        if (isset($data['password'])) $utilisateur->setPassword($hasher->hashPassword($utilisateur, $data['password']));
        $utilisateur->setEditAu(new \DateTime());

        $em->flush();

        return $this->json(['message' => 'Utilisateur mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Utilisateur $utilisateur, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($utilisateur);
        $em->flush();

        return $this->json(['message' => 'Utilisateur supprimé'], 204);
    }
}