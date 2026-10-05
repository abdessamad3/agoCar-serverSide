<?php

namespace App\Repository;

use App\Entity\Vidange;
use App\Entity\Voiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vidange>
 */
class VidangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vidange::class);
    }

    /** @param Voiture[] $voitures @return array<int, Vidange> keyed by voiture ID */
    public function findLatestForVoitures(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }
        $rows = $this->createQueryBuilder('vi')
            ->join('vi.depense', 'd')
            ->join('d.voiture', 'vo')
            ->where('vo IN (:vids)')
            ->andWhere('vi.deletedAt IS NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('vids', $voitures)
            ->orderBy('vi.id', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $vidange) {
            $vid = $vidange->getDepense()?->getVoiture()?->getId();
            if ($vid !== null && !isset($latest[$vid])) {
                $latest[$vid] = $vidange;
            }
        }
        return $latest;
    }

    public function findLatestByVoiture(Voiture $voiture): ?Vidange
    {
        return $this->createQueryBuilder('vi')
            ->join('vi.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('vi.deletedAt IS NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('vi.id', 'DESC')
            ->setMaxResults(1)
            ->setParameter('voiture', $voiture)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns the latest non-deleted Vidange per voiture, keyed by voitureId.
     *
     * @return array<int, Vidange>
     */
    public function findLatestPerVoiture(int $bureauId = 0): array
    {
        $qb = $this->createQueryBuilder('vi')
            ->join('vi.depense', 'd')
            ->join('d.voiture', 'vo')
            ->where('vi.deletedAt IS NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('vi.id', 'DESC');

        if ($bureauId) {
            $qb->andWhere('vo.bureau = :bid')->setParameter('bid', $bureauId);
        }

        $latest = [];
        foreach ($qb->getQuery()->getResult() as $vidange) {
            $vid = $vidange->getDepense()?->getVoiture()?->getId();
            if ($vid !== null && !isset($latest[$vid])) {
                $latest[$vid] = $vidange;
            }
        }
        return $latest;
    }
}
