<?php

namespace App\Repository;

use App\Entity\Bureau;
use App\Entity\Parametres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParametresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parametres::class);
    }

    public function findByBureau(Bureau $bureau): ?Parametres
    {
        return $this->findOneBy(['bureau' => $bureau]);
    }
}
