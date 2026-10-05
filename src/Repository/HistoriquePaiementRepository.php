<?php

namespace App\Repository;

use App\Entity\HistoriquePaiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @deprecated Superseded by {@see \App\Repository\PaiementRepository}. Kept for
 * rollback purposes only — see {@see HistoriquePaiement} for the full rationale.
 */
class HistoriquePaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriquePaiement::class);
    }

    public function countByReservation(int $reservationId): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.reservation = :reservationId')
            ->andWhere('h.deletedAt IS NULL')
            ->setParameter('reservationId', $reservationId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
