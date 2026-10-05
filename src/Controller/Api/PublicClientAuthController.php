<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Public registration + authenticated portal for website clients.
 * ROLE_CLIENT users cannot access any admin (/api) routes.
 */
class PublicClientAuthController extends AbstractController
{
    // ── Registration ─────────────────────────────────────────────────────────

    #[Route('/api/site/client/register', name: 'app_api_public_client_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwt,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = [];
        foreach (['email', 'password', 'fullName'] as $f) {
            if (empty($data[$f])) {
                $errors[$f] = 'Ce champ est requis.';
            }
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide.';
        }
        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 6 caractères.';
        }
        if (!empty($errors)) {
            return $this->json(['error' => 'VALIDATION_ERROR', 'fields' => $errors], 422);
        }

        $email = strtolower(trim($data['email']));

        if ($em->getRepository(Utilisateur::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'EMAIL_TAKEN', 'message' => 'Cet email est déjà utilisé.'], 409);
        }

        $nameParts = explode(' ', trim($data['fullName']), 2);
        $prenom    = count($nameParts) > 1 ? $nameParts[0] : '';
        $nom       = count($nameParts) > 1 ? $nameParts[1] : $nameParts[0];

        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        if (!empty($data['telephone'])) $user->setTelephone(trim($data['telephone']));
        $user->setRoles(['ROLE_CLIENT']);
        $user->setActif(true);
        $user->setPassword($hasher->hashPassword($user, $data['password']));
        $user->setCreeAu(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return $this->json([
            'token' => $jwt->create($user),
            'user'  => $this->serializeUser($user),
        ], 201);
    }

    // ── Client portal (requires ROLE_CLIENT via security.yaml) ───────────────

    #[Route('/api/client-portal/me', name: 'app_api_client_portal_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/client-portal/my-reservations', name: 'app_api_client_portal_reservations', methods: ['GET'])]
    public function myReservations(EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        // Find Client records matching this email, then fetch their reservations directly
        $clients = $em->getRepository(Client::class)->findBy(['email' => $user->getEmail()]);

        if (empty($clients)) {
            return $this->json([]);
        }

        $clientIds = array_map(fn($c) => $c->getId(), $clients);

        $reservations = $em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.client IN (:ids)')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('ids', $clientIds)
            ->orderBy('r.creeAu', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($reservations as $res) {
            $voiture = $res->getVoiture();
            $result[] = [
                'id'           => $res->getId(),
                'ref'          => '#' . str_pad((string) $res->getId(), 6, '0', STR_PAD_LEFT),
                'status'       => $res->getReservationStatus(),
                'dateDebut'    => $res->getDateDebut()?->format('Y-m-d'),
                'dateFin'      => $res->getDateFin()?->format('Y-m-d'),
                'total'        => $res->getTotal(),
                'lieuLivraison'=> $res->getLieuLivraison(),
                'voiture'      => $voiture ? [
                    'marque'          => $voiture->getMarque(),
                    'modele'          => $voiture->getModele(),
                    'annee'           => $voiture->getAnnee(),
                    'immatriculation' => $voiture->getImmatriculation(),
                    'image'           => $voiture->getImages()->first()
                        ? '/uploads/voitures/' . $voiture->getImages()->first()->getImageName()
                        : null,
                ] : null,
                'creeAu'       => $res->getCreeAu()?->format('Y-m-d'),
            ];
        }

        return $this->json($result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function serializeUser(Utilisateur $u): array
    {
        return [
            'id'        => $u->getId(),
            'email'     => $u->getEmail(),
            'nom'       => $u->getNom(),
            'prenom'    => $u->getPrenom(),
            'telephone' => $u->getTelephone(),
        ];
    }
}
