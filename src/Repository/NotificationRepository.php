<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return Notification[] */
    public function findByUser(Utilisateur $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Notification[] */
    public function findByUserAndTab(Utilisateur $user, string $tab, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        match ($tab) {
            'unread'   => $qb->andWhere('n.readAt IS NULL'),
            'critical' => $qb->andWhere('n.priority = :p')->setParameter('p', 'CRITICAL'),
            'warnings' => $qb->andWhere('n.priority IN (:p)')->setParameter('p', ['HIGH', 'MEDIUM']),
            default    => null,
        };

        return $qb->getQuery()->getResult();
    }

    public function countUnread(Utilisateur $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findBySource(string $sourceType, int $sourceId): array
    {
        return $this->findBy(['sourceType' => $sourceType, 'sourceId' => $sourceId]);
    }

    public function findOneByUserAndSource(Utilisateur $user, string $sourceType, int $sourceId): ?Notification
    {
        return $this->findOneBy([
            'user'       => $user,
            'sourceType' => $sourceType,
            'sourceId'   => $sourceId,
        ]);
    }

    public function markAllReadForUser(Utilisateur $user, \DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
