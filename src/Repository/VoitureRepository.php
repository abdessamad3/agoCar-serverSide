<?php

namespace App\Repository;

use App\Entity\Voiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voiture>
 */
class VoitureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voiture::class);
    }

    /**
     * Find cars with filters, search, sort and pagination
     */
    public function findWithFilters(array $filters = [], int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('v');

        // 1. SEARCH (Look in Marque or Modele)
        if (!empty($filters['search'])) {
            $qb->andWhere('v.marque LIKE :search OR v.modele LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // 2. FILTER (By Status)
        if (!empty($filters['voitureStatus'])) {
            $qb->andWhere('v.voitureStatus = :status')
               ->setParameter('status', $filters['voitureStatus']);
        }

        if (!empty($filters['typeCarburant'])) {
            $qb->andWhere('v.typeCarburant = :fuel')
               ->setParameter('fuel', $filters['typeCarburant']);
        }

        // 3. SORT (Dynamic ordering)
        $allowedSortFields = ['marque', 'modele', 'prixJour', 'annee', 'id', 'creeAu'];
        $sortField = $filters['sort'] ?? 'id';
        
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'id';
        }
        
        $sortDir = strtoupper($filters['direction'] ?? 'ASC');
        if (!in_array($sortDir, ['ASC', 'DESC'])) {
            $sortDir = 'ASC';
        }
        
        $qb->orderBy('v.' . $sortField, $sortDir);

        // 4. PAGINATION
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Return IDs of voitures that have an overlapping reservation in the given period
     */
    public function findBookedVoitureIdsForPeriod(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('IDENTITY(r.voiture) as voiture_id')
           ->from(\App\Entity\Reservation::class, 'r')
           ->where('r.dateDebut <= :dateFin')
           ->andWhere('r.dateFin >= :dateDebut')
           ->andWhere('r.reservationStatus != :cancelled')
           ->setParameter('dateDebut', $dateDebut)
           ->setParameter('dateFin', $dateFin)
           ->setParameter('cancelled', 'cancelled');

        return array_column($qb->getQuery()->getScalarResult(), 'voiture_id');
    }

    /**
     * Count total voitures with filters applied
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('v');
        $qb->select('COUNT(v.id)');

        if (!empty($filters['search'])) {
            $qb->andWhere('v.marque LIKE :search OR v.modele LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['voitureStatus'])) {
            $qb->andWhere('v.voitureStatus = :status')
               ->setParameter('status', $filters['voitureStatus']);
        }

        if (!empty($filters['typeCarburant'])) {
            $qb->andWhere('v.typeCarburant = :fuel')
               ->setParameter('fuel', $filters['typeCarburant']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}