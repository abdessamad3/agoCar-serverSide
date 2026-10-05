<?php

namespace App\Repository;

use App\Entity\Paiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    public function countByReservation(int $reservationId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.reservation = :reservationId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('reservationId', $reservationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Most recent payment date across all of a client's reservations, for the Client
     *  Detail financial summary. */
    public function getLastPaymentDate(int $clientId): ?\DateTimeImmutable
    {
        $date = $this->createQueryBuilder('p')
            ->select('p.datePaiement')
            ->join('p.reservation', 'r')
            ->where('r.client = :clientId')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.datePaiement', 'DESC')
            ->setParameter('clientId', $clientId)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return $date ? $date['datePaiement'] : null;
    }

//    /**
//     * @return Paiement[] Returns an array of Paiement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Paiement
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
