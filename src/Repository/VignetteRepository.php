<?php

namespace App\Repository;

use App\Entity\Vignette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vignette>
 */
class VignetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vignette::class);
    }

    public function findLatestByVoiture(\App\Entity\Voiture $voiture): ?Vignette
    {
        return $this->createQueryBuilder('v')
            ->join('v.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('voiture', $voiture)
            ->orderBy('d.dateFin', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @param \App\Entity\Voiture[] $voitures @return array<int, Vignette> keyed by voiture ID */
    public function findLatestForVoitures(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }
        $rows = $this->createQueryBuilder('v')
            ->join('v.depense', 'd')
            ->join('d.voiture', 'vo')
            ->where('vo IN (:vids)')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('vids', $voitures)
            ->orderBy('d.dateFin', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $vignette) {
            $vid = $vignette->getDepense()?->getVoiture()?->getId();
            if ($vid !== null && !isset($latest[$vid])) {
                $latest[$vid] = $vignette;
            }
        }
        return $latest;
    }
}
