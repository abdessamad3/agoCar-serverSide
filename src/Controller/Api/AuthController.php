<?php

namespace App\Controller\Api;

use App\Entity\RefreshToken;
use App\Entity\Utilisateur;
use App\Repository\RefreshTokenRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth', name: 'app_api_auth_')]
class AuthController extends AbstractController
{
    use ApiResponseTrait;

    private const PASSWORD_PATTERN = '/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+\[\]{}|;:,.<>?\/\\\\])/';

    public function __construct(private RateLimiterFactory $loginLimiter) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->error('Email and password required');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email format');
        }

        $pwd = $data['password'];
        if (strlen($pwd) < 8) {
            return $this->error('Password must be at least 8 characters');
        }
        if (!preg_match(self::PASSWORD_PATTERN, $pwd)) {
            return $this->error('Password must contain at least one uppercase letter, one number, and one special character');
        }

        $existing = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->error('User already exists', 409);
        }

        $user = new Utilisateur();
        $user->setEmail($data['email']);
        $user->setPassword($hasher->hashPassword($user, $pwd));
        $user->setNom($data['nom'] ?? '');
        $user->setPrenom($data['prenom'] ?? '');
        $user->setRoles(['ROLE_USER']);
        $user->setCreeAu(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return $this->created(['id' => $user->getId()], 'User registered successfully');
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $tokenManager,
        RefreshTokenRepository $refreshRepo
    ): JsonResponse {
        $limiter = $this->loginLimiter->create($request->getClientIp() ?? 'unknown');
        $limit   = $limiter->consume();

        if (!$limit->isAccepted()) {
            return $this->error('Too many login attempts. Please wait before trying again.', 429);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->error('Email and password required');
        }

        $user = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
        if (!$user || !$hasher->isPasswordValid($user, $data['password'])) {
            return $this->error('Invalid credentials', 401);
        }

        if (!$user->isActif()) {
            return $this->error('Account is disabled', 403);
        }

        $user->setLastActivityAt(new \DateTimeImmutable());
        $accessToken  = $tokenManager->create($user);
        $refreshToken = $this->generateRefreshToken($user, $em);
        $em->flush();

        return $this->success([
            'token'        => $accessToken,
            'refreshToken' => $refreshToken,
            'user'         => [
                'id'                 => $user->getId(),
                'email'              => $user->getEmail(),
                'nom'                => $user->getNom(),
                'prenom'             => $user->getPrenom(),
                'roles'              => $user->getRoles(),
                'bureau'             => $user->getBureau()?->getId(),
                'mustChangePassword' => $user->isMustChangePassword(),
            ],
        ]);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $tokenManager,
        RefreshTokenRepository $refreshRepo
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $token = $data['refreshToken'] ?? $request->headers->get('X-Refresh-Token');

        if (!$token) {
            return $this->error('Refresh token required');
        }

        $rt = $refreshRepo->findValidToken($token);
        if (!$rt) {
            // Could be a genuinely invalid/expired token, or a benign race: several near-
            // simultaneous refresh calls with the same token (multiple open tabs, or a dev-server
            // reload racing an in-flight request) — earlier ones already rotated it. If THIS exact
            // token was rotated, walk the replacement chain instead of failing, so a late racer
            // doesn't force-logout a user who was never actually inactive.
            $cursor = $token;
            for ($hop = 0; $hop < 5 && !$rt; $hop++) {
                $stale = $refreshRepo->findOneBy(['token' => $cursor]);
                if (!$stale || !$stale->getReplacedByToken()) {
                    break;
                }
                $cursor = $stale->getReplacedByToken();
                $rt = $refreshRepo->findValidToken($cursor);
            }
            if (!$rt) {
                return $this->error('Invalid or expired refresh token', 401);
            }
        }

        // Rotate: revoke old token, issue new pair
        $rt->setRevoked(true);
        $user = $rt->getUtilisateur();

        $accessToken     = $tokenManager->create($user);
        $newRefreshToken = $this->generateRefreshToken($user, $em);
        $rt->setReplacedByToken($newRefreshToken);
        $em->flush();

        return $this->success([
            'token'        => $accessToken,
            'refreshToken' => $newRefreshToken,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        RefreshTokenRepository $refreshRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $token = $data['refreshToken'] ?? $request->headers->get('X-Refresh-Token');

        if ($token) {
            $rt = $refreshRepo->findValidToken($token);
            if ($rt) {
                $rt->setRevoked(true);
                $em->flush();
            }
        }

        return $this->success(null, 'Logged out');
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->error('Not authenticated', 401);
        }

        return $this->success([
            'id'                 => $user->getId(),
            'email'              => $user->getEmail(),
            'nom'                => $user->getNom(),
            'prenom'             => $user->getPrenom(),
            'telephone'          => $user->getTelephone(),
            'photo'              => $user->getPhoto(),
            'roles'              => $user->getRoles(),
            'bureau'             => $user->getBureau()?->getId(),
            'bureauNom'          => $user->getBureau()?->getNom(),
            'companyId'          => $user->getBureau()?->getCompany()?->getId(),
            'companyNom'         => $user->getBureau()?->getCompany()?->getNom(),
            'companyLogo'        => $user->getBureau()?->getCompany()?->getLogo(),
            'creeAu'             => $user->getCreeAu()?->format('Y-m-d H:i:s'),
            'mustChangePassword' => $user->isMustChangePassword(),
            'signatureBlob'      => $user->getSignatureBlob(),
        ]);
    }

    private function generateRefreshToken(Utilisateur $user, EntityManagerInterface $em): string
    {
        $tokenValue = bin2hex(random_bytes(64));

        $rt = new RefreshToken();
        $rt->setToken($tokenValue);
        $rt->setUtilisateur($user);
        $rt->setCreatedAt(new \DateTimeImmutable());
        $rt->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $em->persist($rt);

        return $tokenValue;
    }
}
