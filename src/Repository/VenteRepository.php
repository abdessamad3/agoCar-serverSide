<?php

namespace App\Repository;

use App\Entity\Vente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VenteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vente::class);
    }

    public function findByBureau(?int $bureauId): array
    {
        $qb = $this->createQueryBuilder('v')
            ->where('v.deletedAt IS NULL')
            ->orderBy('v.dateVente', 'DESC');

        if ($bureauId) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        return $qb->getQuery()->getResult();
    }
}
