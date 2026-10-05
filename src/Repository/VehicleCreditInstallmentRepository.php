<?php

namespace App\Repository;

use App\Entity\VehicleCreditInstallment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleCreditInstallment>
 */
class VehicleCreditInstallmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleCreditInstallment::class);
    }

    /**
     * Find the next unpaid installment for a given VehicleCredit (earliest due date).
     */
    public function findNextUnpaid(int $vehicleCreditId): ?VehicleCreditInstallment
    {
        return $this->createQueryBuilder('i')
            ->where('i.vehicleCredit = :cid')
            ->andWhere("i.status NOT IN ('paid')")
            ->setParameter('cid', $vehicleCreditId)
            ->orderBy('i.dueDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all installments due within $days days that are not yet paid.
     *
     * @return VehicleCreditInstallment[]
     */
    public function findUpcoming(int $days = 5): array
    {
        $today  = new \DateTimeImmutable('today');
        $cutoff = $today->modify("+{$days} days");

        return $this->createQueryBuilder('i')
            ->where("i.status NOT IN ('paid')")
            ->andWhere('i.dueDate <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
