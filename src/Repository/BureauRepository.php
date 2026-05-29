<?php

namespace App\Repository;

use App\Entity\Bureau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bureau>
 */
class BureauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bureau::class);
    }

    /**
     * Find bureaus with filters, pagination, and sorting
     * 
     * @param array $filters {search, statut, sort, direction}
     * @param int $page
     * @param int $limit
     * @return Bureau[]
     */
    public function findWithFilters(array $filters, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('b');

        // Search by nom
        if (!empty($filters['search'])) {
            $qb->andWhere('b.nom LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Filter by statut
        if (!empty($filters['statut'])) {
            $qb->andWhere('b.statut = :statut')
               ->setParameter('statut', $filters['statut']);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'ASC';
        $qb->orderBy('b.' . $sort, $direction);

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Count bureaus with filters
     * 
     * @param array $filters
     * @return int
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)');

        if (!empty($filters['search'])) {
            $qb->andWhere('b.nom LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['statut'])) {
            $qb->andWhere('b.statut = :statut')
               ->setParameter('statut', $filters['statut']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find active bureaus only
     * 
     * @return Bureau[]
     */
    public function findActive(): array
    {
        return $this->findBy(['statut' => 'actif'], ['nom' => 'ASC']);
    }

    /**
     * Search bureaus by nom
     * 
     * @param string $search
     * @return Bureau[]
     */
    public function searchByNom(string $search): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.nom LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('b.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}