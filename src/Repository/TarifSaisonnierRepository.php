<?php

namespace App\Repository;

use App\Entity\TarifSaisonnier;
use App\Entity\Voiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TarifSaisonnier>
 */
class TarifSaisonnierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifSaisonnier::class);
    }

    /**
     * The rule in effect for a vehicle on a given date, if any. A rule scoped to this
     * specific vehicle wins over one that applies to every vehicle if both match;
     * among ties, the most recently created rule wins. Rules aren't expected to
     * normally overlap at the same scope, so this is a tie-break, not validation.
     */
    public function findActiveMatching(Voiture $voiture, \DateTimeImmutable $date): ?TarifSaisonnier
    {
        return $this->createQueryBuilder('t')
            ->where('t.deletedAt IS NULL')
            ->andWhere('t.actif = true')
            ->andWhere('t.dateDebut <= :date')
            ->andWhere('t.dateFin >= :date')
            ->andWhere('t.voiture IS NULL OR t.voiture = :voiture')
            ->setParameter('date', $date->setTime(0, 0))
            ->setParameter('voiture', $voiture)
            ->orderBy('t.voiture', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
