<?php

namespace App\Repository;

use App\Entity\Bureau;
use App\Entity\RecurringExpenseTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecurringExpenseTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringExpenseTemplate::class);
    }

    /** @return RecurringExpenseTemplate[] */
    public function findActiveByBureau(Bureau $bureau): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.bureau = :bureau')
            ->andWhere('t.isActive = true')
            ->setParameter('bureau', $bureau)
            ->getQuery()
            ->getResult();
    }

    /** @return RecurringExpenseTemplate[] */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->join('t.bureau', 'b')
            ->getQuery()
            ->getResult();
    }

    public function findOneByBureauAndType(Bureau $bureau, string $type): ?RecurringExpenseTemplate
    {
        return $this->createQueryBuilder('t')
            ->where('t.bureau = :bureau')
            ->andWhere('t.typeDepense = :type')
            ->setParameter('bureau', $bureau)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
