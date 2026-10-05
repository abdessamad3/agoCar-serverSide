<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/password', name: 'app_api_password_')]
class PasswordController extends AbstractController
{
    #[Route('/change', name: 'change', methods: ['POST'])]
    public function change(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['currentPassword']) || empty($data['newPassword'])) {
            return $this->json(['error' => 'currentPassword and newPassword are required'], 400);
        }

        if (!$hasher->isPasswordValid($user, $data['currentPassword'])) {
            $attempts = $user->getFailedPasswordAttempts() + 1;
            $user->setFailedPasswordAttempts($attempts);
            $em->flush();
            return $this->json(['error' => 'Current password is incorrect'], 400);
        }

        $error = $this->validateStrength($data['newPassword']);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        $user->setPassword($hasher->hashPassword($user, $data['newPassword']));
        $user->setMustChangePassword(false);
        $user->setLastPasswordChangeAt(new \DateTimeImmutable());
        $user->setFailedPasswordAttempts(0);
        $user->setPasswordLockedUntil(null);
        $user->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Password changed successfully']);
    }

    #[Route('/admin/reset/{id}', name: 'admin_reset', methods: ['POST'])]
    public function adminReset(
        Utilisateur $utilisateur,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return $this->json(['error' => 'Admin access required'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['newPassword'])) {
            return $this->json(['error' => 'newPassword is required'], 400);
        }

        $error = $this->validateStrength($data['newPassword']);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        $forceChange = isset($data['forceChange']) ? (bool) $data['forceChange'] : true;

        $utilisateur->setPassword($hasher->hashPassword($utilisateur, $data['newPassword']));
        $utilisateur->setMustChangePassword($forceChange);
        $utilisateur->setLastPasswordChangeAt(new \DateTimeImmutable());
        $utilisateur->setFailedPasswordAttempts(0);
        $utilisateur->setPasswordLockedUntil(null);
        $utilisateur->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Password reset successfully']);
    }

    private function validateStrength(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain at least one special character';
        }
        return null;
    }
}
