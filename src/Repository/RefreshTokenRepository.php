<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidToken(string $token): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.revoked = false')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Revoke all refresh tokens for a user (forced logout). */
    public function revokeAllForUser(Utilisateur $user): void
    {
        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revoked', true)
            ->where('rt.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /** Delete expired and revoked tokens older than 30 days. */
    public function purgeExpired(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :cutoff OR rt.revoked = true')
            ->setParameter('cutoff', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->execute();
    }
}
