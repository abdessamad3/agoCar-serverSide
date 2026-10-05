<?php

namespace App\Service;

use App\Entity\Damage;
use App\Entity\Reparation;
use App\Entity\Reservation;
use App\Doctrine\VoitureStatusWriteGuard;
use App\Entity\Voiture;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes and persists the effective voitureStatus by mirroring the
 * frontend effectiveStatus() logic: vendu/archive override, then
 * hors_service (expired compliance), then maintenance (active repair),
 * then louee (active reservation), then disponible.
 */
class VoitureStatusService
{
    private const MANUAL_STATUSES   = ['vendu', 'archive', 'brouillon', 'setup', 'decommissioned', 'reserve'];
    private const CANCELLED_STATUSES = ['annulee', 'annule'];

    public function __construct(
        private EntityManagerInterface  $em,
        private ComplianceService       $complianceService,
        private VoitureStatusWriteGuard $guard,
    ) {}

    public function computeStatus(Voiture $voiture): string
    {
        $stored = strtolower($voiture->getVoitureStatus() ?? '');

        if (in_array($stored, self::MANUAL_STATUSES)) {
            return $stored;
        }

        // Expired compliance → hors_service
        $compliance = $this->complianceService->getComplianceStatus($voiture);
        if ($compliance['overall'] === ComplianceService::EXPIRED) {
            return 'hors_service';
        }

        $today = new \DateTimeImmutable('today');

        // Active repair (not soft-deleted, dateFin null or in the future -- a
        // repair completed today is already done, not still blocking today)
        $hasRepair = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        if ($hasRepair > 0) {
            return 'maintenance';
        }

        // Open damage — via return inspection OR direct voiture link (manual damage)
        $dQb = $this->em->createQueryBuilder();
        $hasOpenDamage = (int) $dQb
            ->select('COUNT(dmg.id)')
            ->from(Damage::class, 'dmg')
            ->leftJoin('dmg.returnInspection', 'ri')
            ->leftJoin('ri.reservation', 'res')
            ->where($dQb->expr()->orX('res.voiture = :voiture', 'dmg.voiture = :voiture'))
            ->andWhere('dmg.status = :open')
            ->setParameter('voiture', $voiture)
            ->setParameter('open', 'open')
            ->getQuery()->getSingleScalarResult();

        if ($hasOpenDamage > 0) {
            return 'maintenance';
        }

        // Active reservation (not soft-deleted, not cancelled, date range covers today)
        $hasReservation = (int) $this->em->createQueryBuilder()
            ->select('COUNT(res.id)')
            ->from(Reservation::class, 'res')
            ->where('res.voiture = :voiture')
            ->andWhere('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut <= :today')
            ->andWhere('res.dateFin >= :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('cancelled', self::CANCELLED_STATUSES)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return $hasReservation > 0 ? 'louee' : 'disponible';
    }

    /**
     * Efficiently computes effective statuses for multiple cars.
     * Uses ComplianceService::getComplianceStatusBulk so the list and detail
     * endpoints apply identical compliance logic — no more list/detail discrepancy.
     *
     * Priority:
     *   vendu / archive / brouillon / setup / decommissioned / reserve → stored (manual)
     *   compliance EXPIRED → 'hors_service'
     *   active repair or open damage today → 'maintenance'
     *   active reservation today → 'louee'
     *   otherwise → 'disponible'
     */
    public function computeStatusBulk(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }

        $result = [];
        $dynamic = [];
        foreach ($voitures as $v) {
            $stored = strtolower($v->getVoitureStatus() ?? '');
            if (in_array($stored, self::MANUAL_STATUSES)) {
                $result[$v->getId()] = $stored;
            } else {
                $dynamic[] = $v;
            }
        }

        if (empty($dynamic)) {
            return $result;
        }

        $today = new \DateTimeImmutable('today');

        // Compliance — same service as computeStatus/detail endpoint (eliminates list/detail split)
        $complianceMap = $this->complianceService->getComplianceStatusBulk($dynamic);

        // Active repairs today
        $repairRows = $this->em->createQueryBuilder()
            ->select('DISTINCT v.id')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->join('d.voiture', 'v')
            ->where('v IN (:vids)')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('vids', $dynamic)
            ->setParameter('today', $today)
            ->getQuery()
            ->getScalarResult();
        $repairIds = array_flip(array_column($repairRows, 'id'));

        // Active reservations today
        $reservedRows = $this->em->createQueryBuilder()
            ->select('DISTINCT v.id')
            ->from(Reservation::class, 'res')
            ->join('res.voiture', 'v')
            ->where('v IN (:vids)')
            ->andWhere('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut <= :today')
            ->andWhere('res.dateFin >= :today')
            ->setParameter('vids', $dynamic)
            ->setParameter('cancelled', self::CANCELLED_STATUSES)
            ->setParameter('today', $today)
            ->getQuery()
            ->getScalarResult();
        $reservedIds = array_flip(array_column($reservedRows, 'id'));

        // Open damage — via return inspection
        $dmgViaInspection = $this->em->createQueryBuilder()
            ->select('DISTINCT v.id')
            ->from(Damage::class, 'dmg')
            ->join('dmg.returnInspection', 'ri')
            ->join('ri.reservation', 'res')
            ->join('res.voiture', 'v')
            ->where('v IN (:vids)')
            ->andWhere('dmg.status = :open')
            ->setParameter('vids', $dynamic)
            ->setParameter('open', 'open')
            ->getQuery()->getScalarResult();
        // Open damage — direct voiture link (manual damage)
        $dmgViaDirect = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(dmg.voiture) as id')
            ->from(Damage::class, 'dmg')
            ->where('dmg.voiture IN (:vids)')
            ->andWhere('dmg.status = :open')
            ->setParameter('vids', $dynamic)
            ->setParameter('open', 'open')
            ->getQuery()->getScalarResult();
        $hasOpenDamage = array_flip(array_unique(array_merge(
            array_column($dmgViaInspection, 'id'),
            array_column($dmgViaDirect, 'id'),
        )));

        foreach ($dynamic as $v) {
            $vid               = $v->getId();
            $complianceExpired = ($complianceMap[$vid]['overall'] ?? ComplianceService::UNKNOWN) === ComplianceService::EXPIRED;

            if ($complianceExpired) {
                $result[$vid] = 'hors_service';
            } elseif (isset($repairIds[$vid]) || isset($hasOpenDamage[$vid])) {
                $result[$vid] = 'maintenance';
            } elseif (isset($reservedIds[$vid])) {
                $result[$vid] = 'louee';
            } else {
                $result[$vid] = 'disponible';
            }
        }

        return $result;
    }

    /**
     * Recomputes and saves the status for one car.
     * Caller must call em->flush() if batching multiple cars.
     */
    public function syncStatus(Voiture $voiture, bool $flush = true): void
    {
        $newStatus = $this->computeStatus($voiture);
        if ($voiture->getVoitureStatus() !== $newStatus) {
            $voiture->setVoitureStatus($newStatus);
            if ($flush) {
                $this->guard->activate();
                try {
                    $this->em->flush();
                } finally {
                    $this->guard->deactivate();
                }
            }
        }
    }

    /**
     * Recomputes and saves status for all non-deleted, non-manual cars.
     * Returns the count of cars whose status changed.
     */
    public function syncAll(): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Voiture::class, 'v')
            ->where('v.deletedAt IS NULL')
            ->andWhere('v.voitureStatus NOT IN (:manual)')
            ->setParameter('manual', self::MANUAL_STATUSES);

        /** @var Voiture[] $cars */
        $cars = $qb->getQuery()->getResult();
        $changed = 0;

        foreach ($cars as $car) {
            $newStatus = $this->computeStatus($car);
            if ($car->getVoitureStatus() !== $newStatus) {
                $car->setVoitureStatus($newStatus);
                $changed++;
            }
        }

        if ($changed > 0) {
            $this->guard->activate();
            try {
                $this->em->flush();
            } finally {
                $this->guard->deactivate();
            }
        }

        return $changed;
    }
}
