<?php

namespace App\Repository;

use App\Entity\PaiementDepense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaiementDepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaiementDepense::class);
    }

    public function findByDepense(int $depenseId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.depense = :depenseId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('depenseId', $depenseId)
            ->orderBy('p.datePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByDepense(int $depenseId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.depense = :depenseId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('depenseId', $depenseId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
