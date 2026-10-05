<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /** Returns companies where the user is manager OR a staff member. */
    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.staff', 's')
            ->where('c.manager = :u OR s = :u')
            ->setParameter('u', $user)
            ->orderBy('c.creeAu', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
