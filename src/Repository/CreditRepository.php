<?php

namespace App\Repository;

use App\Entity\Credit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @deprecated Superseded by {@see \App\Repository\VehicleCreditRepository}.
 * Kept for rollback purposes only — see {@see Credit} for the full rationale.
 * @extends ServiceEntityRepository<Credit>
 */
class CreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credit::class);
    }

    /** @return Credit[] credits that are not deleted and not fully closed */
    public function findActive(): array
    {
        $closed = ['terminé', 'termine', 'annulé', 'annule', 'paye', 'payé'];
        return $this->createQueryBuilder('c')
            ->where('c.deletedAt IS NULL')
            ->andWhere('c.statut NOT IN (:closed)')
            ->setParameter('closed', $closed)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Credit[] Returns an array of Credit objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Credit
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
