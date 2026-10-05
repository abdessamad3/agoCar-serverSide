<?php

namespace App\Repository;

use App\Entity\ErrorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ErrorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ErrorLog::class);
    }

    /** @return ErrorLog[] */
    public function findFiltered(?string $source, ?string $dateFrom, ?string $dateTo, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')->addSelect('u')
            ->orderBy('e.createdAt', 'DESC');
        $this->applyFilters($qb, $source, $dateFrom, $dateTo);

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFiltered(?string $source, ?string $dateFrom, ?string $dateTo): int
    {
        $qb = $this->createQueryBuilder('e')->select('COUNT(e.id)');
        $this->applyFilters($qb, $source, $dateFrom, $dateTo);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findLatest(): ?ErrorLog
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?string $source, ?string $dateFrom, ?string $dateTo): void
    {
        if ($source) {
            $qb->andWhere('e.source = :source')->setParameter('source', $source);
        }
        if ($dateFrom) {
            $qb->andWhere('e.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }
        if ($dateTo) {
            $qb->andWhere('e.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }
    }
}
