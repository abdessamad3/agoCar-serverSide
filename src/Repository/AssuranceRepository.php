<?php

namespace App\Repository;

use App\Entity\Assurance;
use App\Repository\Trait\DepenseOverlapTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assurance>
 */
class AssuranceRepository extends ServiceEntityRepository
{
    use DepenseOverlapTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assurance::class);
    }

    public function findLatestByVoiture(\App\Entity\Voiture $voiture): ?Assurance
    {
        return $this->createQueryBuilder('a')
            ->join('a.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('a.archivedAt IS NULL')
            ->andWhere('a.cancelledAt IS NULL')
            ->setParameter('voiture', $voiture)
            ->orderBy('d.dateFin', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @param \App\Entity\Voiture[] $voitures @return array<int, Assurance> keyed by voiture ID */
    public function findLatestForVoitures(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }
        $rows = $this->createQueryBuilder('a')
            ->join('a.depense', 'd')
            ->join('d.voiture', 'vo')
            ->where('vo IN (:vids)')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.archivedAt IS NULL')
            ->andWhere('a.cancelledAt IS NULL')
            ->setParameter('vids', $voitures)
            ->orderBy('d.dateFin', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $assurance) {
            $vid = $assurance->getDepense()?->getVoiture()?->getId();
            if ($vid !== null && !isset($latest[$vid])) {
                $latest[$vid] = $assurance;
            }
        }
        return $latest;
    }

    public function findActiveByVoiture(\App\Entity\Voiture $voiture): ?Assurance
    {
        return $this->createQueryBuilder('a')
            ->join('a.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.archivedAt IS NULL')
            ->andWhere('a.cancelledAt IS NULL')
            ->setParameter('voiture', $voiture)
            ->orderBy('d.dateFin', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasOverlap(\App\Entity\Voiture $voiture, \DateTimeImmutable $debut, \DateTimeImmutable $fin, ?int $excludeId = null): bool
    {
        return $this->hasDepenseOverlap('a', $voiture, $debut, $fin, $excludeId, ['archivedAt', 'cancelledAt']);
    }

//    /**
//     * @return Assurance[] Returns an array of Assurance objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Assurance
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
