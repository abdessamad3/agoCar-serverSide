<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Paginated filtered list for the admin UI.
     *
     * @return ActivityLog[]
     */
    public function findFiltered(
        ?string $entityType,
        ?string $action,
        ?int    $userId,
        ?int    $bureauId,
        ?string $dateFrom,
        ?string $dateTo,
        int     $page  = 1,
        int     $limit = 50,
    ): array {
        $qb = $this->createQueryBuilder('al')
            ->leftJoin('al.user',   'u')
            ->leftJoin('al.bureau', 'b')
            ->addSelect('u', 'b')
            ->orderBy('al.createdAt', 'DESC');

        $this->applyFilters($qb, $entityType, $action, $userId, $bureauId, $dateFrom, $dateTo);

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFiltered(
        ?string $entityType,
        ?string $action,
        ?int    $userId,
        ?int    $bureauId,
        ?string $dateFrom,
        ?string $dateTo,
    ): int {
        $qb = $this->createQueryBuilder('al')->select('COUNT(al.id)');
        $this->applyFilters($qb, $entityType, $action, $userId, $bureauId, $dateFrom, $dateTo);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** History for one specific entity (e.g. all changes to Voiture #12). */
    public function findByEntity(string $entityType, int $entityId, int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')->addSelect('u')
            ->where('al.entityType = :type')
            ->andWhere('al.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ...$args): void
    {
        [$entityType, $action, $userId, $bureauId, $dateFrom, $dateTo] = $args;

        if ($entityType) {
            $qb->andWhere('al.entityType = :entityType')->setParameter('entityType', $entityType);
        }
        if ($action) {
            $qb->andWhere('al.action = :action')->setParameter('action', $action);
        }
        if ($userId) {
            $qb->andWhere('u.id = :userId')->setParameter('userId', $userId);
        }
        if ($bureauId) {
            $qb->andWhere('b.id = :bureauId')->setParameter('bureauId', $bureauId);
        }
        if ($dateFrom) {
            $qb->andWhere('al.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if ($dateTo) {
            $qb->andWhere('al.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }
    }
}
