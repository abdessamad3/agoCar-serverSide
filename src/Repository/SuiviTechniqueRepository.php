<?php

namespace App\Repository;

use App\Entity\SuiviTechnique;
use App\Repository\Trait\DepenseOverlapTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuiviTechnique>
 */
class SuiviTechniqueRepository extends ServiceEntityRepository
{
    use DepenseOverlapTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuiviTechnique::class);
    }

    public function findLatestByVoiture(\App\Entity\Voiture $voiture): ?SuiviTechnique
    {
        return $this->createQueryBuilder('s')
            ->join('s.depense', 'd')
            ->where('s.voiture = :voiture')
            ->setParameter('voiture', $voiture)
            ->orderBy('d.dateFin', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @param \App\Entity\Voiture[] $voitures @return array<int, SuiviTechnique> keyed by voiture ID */
    public function findLatestForVoitures(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }
        $rows = $this->createQueryBuilder('s')
            ->join('s.depense', 'd')
            ->where('s.voiture IN (:vids)')
            ->setParameter('vids', $voitures)
            ->orderBy('d.dateFin', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $suivi) {
            $vid = $suivi->getVoiture()?->getId();
            if ($vid !== null && !isset($latest[$vid])) {
                $latest[$vid] = $suivi;
            }
        }
        return $latest;
    }

    public function hasOverlap(\App\Entity\Voiture $voiture, \DateTimeImmutable $debut, \DateTimeImmutable $fin, ?int $excludeId = null): bool
    {
        return $this->hasDepenseOverlap('s', $voiture, $debut, $fin, $excludeId);
    }

//    /**
//     * @return SuiviTechnique[] Returns an array of SuiviTechnique objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?SuiviTechnique
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
