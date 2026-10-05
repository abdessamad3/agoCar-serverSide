<?php

namespace App\Repository;

use App\Entity\VehicleCredit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleCredit>
 *
 * This file was missing entirely despite VehicleCredit's #[ORM\Entity(
 * repositoryClass: VehicleCreditRepository::class)] attribute referencing it,
 * and despite VehicleCreditController, VehicleCreditDocumentController, and
 * VehicleCreditPaymentController all directly injecting it as a constructor/
 * action argument — confirmed via `debug:autowiring` returning no match.
 * Every endpoint touching the VehicleCredit entity was throwing a fatal
 * "class not found" error until this was added.
 */
class VehicleCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleCredit::class);
    }
}
