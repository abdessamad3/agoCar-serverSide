<?php

namespace App\Repository;

use App\Entity\DeuxiemeChauffeur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeuxiemeChauffeur>
 */
class DeuxiemeChauffeurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeuxiemeChauffeur::class);
    }
}
