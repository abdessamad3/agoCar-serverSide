<?php

namespace App\Fleet;

use App\Entity\AchatVoiture;
use App\Entity\Assurance;
use App\Entity\Damage;
use App\Entity\Depense;
use App\Entity\Reparation;
use App\Entity\Reservation;
use App\Entity\SuiviTechnique;
use App\Entity\Vignette;
use App\Entity\Voiture;
use App\Enum\VehicleLifecycleState;
use App\Fleet\Event\DamageRecorded;
use App\Fleet\Event\DamageRepairLinked;
use App\Fleet\Event\DecommissionInitiated;
use App\Fleet\Event\FleetEvent;
use App\Fleet\Event\InsuranceCreated;
use App\Fleet\Event\InsuranceExpired;
use App\Fleet\Event\InspectionCreated;
use App\Fleet\Event\InspectionExpired;
use App\Fleet\Event\OilChangeRecorded;
use App\Fleet\Event\RentalStarted;
use App\Fleet\Event\RepairCompleted;
use App\Fleet\Event\RepairStarted;
use App\Fleet\Event\ReservationConfirmed;
use App\Fleet\Event\ReturnInspectionCompleted;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Fleet\Event\VehicleArchived;
use App\Fleet\Event\VehicleDraftCreated;
use App\Fleet\Event\VehiclePurchaseRecorded;
use App\Fleet\Event\VehicleSold;
use App\Fleet\Event\VignetteCreated;
use App\Fleet\Event\VignetteExpired;
use App\Fleet\Exception\InvalidTransitionException;
use App\Fleet\Exception\LifecycleViolationException;
use App\Doctrine\VoitureStatusWriteGuard;
use App\Repository\AssuranceRepository;
use App\Service\ActivityLogService;
use App\Service\ComplianceService;
use App\Service\NotificationService;
use App\Service\OilChangeService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single orchestration layer for all vehicle lifecycle state changes.
 *
 * RULES:
 *   - Controllers call applyEvent() — never write voitureStatus directly.
 *   - Guards (assert*) are called by controllers BEFORE persisting the domain entity.
 *   - applyEvent() is called AFTER the domain entity is persisted (but before flush).
 *   - applyEvent() does a single em->flush() at the end — all changes are atomic.
 *
 * WHAT THIS REPLACES:
 *   - VoitureStatusService::computeStatus()    → resolveState()
 *   - VoitureStatusService::syncStatus()       → applyEvent() output
 *   - VoitureStatusService::syncAll()          → syncAll()
 *   - Direct voitureStatus writes in controllers → applyEvent()
 *
 * WHAT THIS DOES NOT REPLACE:
 *   - ComplianceService   (read-only dependency)
 *   - OilChangeService    (read-only dependency)
 *   - NotificationService (side-effect dependency)
 *   - ActivityLogService  (side-effect dependency)
 *   - DepensePaymentService (financial domain — untouched)
 *   - ProfitabilityService  (reporting domain — untouched)
 */
class FleetLifecycleManager
{
    /**
     * Permitted state transitions.
     * Key = from, Value = array of permitted to-states.
     * resolveState() may only produce a to-state that appears in this map.
     */
    private const VALID_TRANSITIONS = [
        'brouillon'      => ['setup', 'disponible'],
        'setup'          => ['disponible', 'hors_service', 'vendu', 'archive'],
        'disponible'     => ['louee', 'reserve', 'maintenance', 'hors_service', 'decommissioned', 'archive', 'vendu'],
        'reserve'        => ['louee', 'disponible', 'hors_service', 'maintenance'],
        'louee'          => ['disponible', 'maintenance', 'hors_service'],
        'maintenance'    => ['disponible', 'hors_service', 'decommissioned'],
        'hors_service'   => ['disponible', 'maintenance', 'decommissioned', 'vendu'],
        'decommissioned' => ['vendu', 'disponible'],
        'vendu'          => [],         // terminal
        'archive'        => ['disponible'],
    ];

    /** Statuses that the TEMPORAL sync is allowed to modify. */
    private const TEMPORAL_SYNCABLE = ['disponible', 'reserve', 'louee', 'maintenance', 'hors_service', 'setup'];

    private const CANCELLED_STATUSES = ['annulee', 'annule'];

    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly ComplianceService       $complianceService,
        private readonly OilChangeService        $oilChangeService,
        private readonly ActivityLogService      $activityLog,
        private readonly NotificationService     $notificationService,
        private readonly VoitureStatusWriteGuard $guard,
    ) {}

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Main entry point. Call this after persisting your domain entity (Assurance,
     * Reparation, etc.) but BEFORE em->flush(). This method does the final flush.
     */
    public function applyEvent(Voiture $voiture, FleetEvent $event): LifecycleResult
    {
        $snapshot     = $this->buildSnapshot($voiture);
        $previousState = VehicleLifecycleState::tryFrom($voiture->getVoitureStatus() ?? '')
            ?? VehicleLifecycleState::DRAFT;

        $warnings    = [];
        $postEffects = [];

        // Temporal events only touch vehicles that allow sync
        if ($event instanceof TemporalSyncTriggered
            && !in_array($voiture->getVoitureStatus(), self::TEMPORAL_SYNCABLE, true)
        ) {
            return LifecycleResult::noChange($previousState);
        }

        // Resolve new state — event may mandate a target, otherwise use the rule engine
        $mandatedState = $this->resolveEventMandatedState($event);
        $newState = $mandatedState ?? $this->resolveStateFromSnapshot($snapshot);

        // Validate the transition is in the matrix
        if ($newState !== $previousState) {
            $this->assertValidTransition($previousState, $newState, $voiture->getId());
        }

        // Guard must be active for this entire block, not just the final flush() call below:
        // activityLog->logUpdate() and notificationService do their OWN internal $em->flush()
        // calls for their own entities, and since flush() processes the WHOLE unit of work, a
        // dirty voitureStatus on $voiture would hit the guard during THEIR flush too if it
        // weren't already active here.
        $this->guard->activate();
        try {
            // Run event-specific post-effects (mileage sync, damage links, benefice calc, etc.)
            $postEffects = $this->runPostEffects($voiture, $event, $snapshot);

            // Advisory oil warning
            if ($snapshot->oilStatus === OilChangeService::OVERDUE) {
                $warnings[] = sprintf('Oil change overdue by %d km', abs($snapshot->oilRemainingKm ?? 0));
            } elseif ($snapshot->oilStatus === OilChangeService::DUE_SOON) {
                $warnings[] = sprintf('Oil change due in %d km', $snapshot->oilRemainingKm ?? 0);
            }

            // Apply state transition if changed
            $stateChanged = false;
            if ($newState !== $previousState) {
                $voiture->setVoitureStatus($newState->value);
                $stateChanged = true;

                $this->activityLog->logUpdate(
                    'Voiture',
                    $voiture->getId(),
                    ['voitureStatus' => $previousState->value, 'event' => null],
                    ['voitureStatus' => $newState->value,      'event' => $event->getEventName()],
                    $voiture->getBureau(),
                );

                $this->dispatchNotificationForTransition($voiture, $previousState, $newState, $event);
            }

            // Final flush — covers any change not already persisted by the calls above.
            $this->em->flush();
        } finally {
            $this->guard->deactivate();
        }

        return $stateChanged
            ? LifecycleResult::transitioned($previousState, $newState, $warnings, $postEffects)
            : LifecycleResult::noChange($previousState);
    }

    /**
     * Compute what state a vehicle should be in right now, based on live data.
     * Does NOT write anything. Safe to call for read-only checks.
     */
    public function resolveState(Voiture $voiture): VehicleLifecycleState
    {
        return $this->resolveStateFromSnapshot($this->buildSnapshot($voiture));
    }

    /**
     * Nightly batch re-sync. Replaces VoitureStatusService::syncAll().
     * Returns the count of vehicles whose state changed.
     */
    public function syncAll(?int $bureauId = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Voiture::class, 'v')
            ->where('v.deletedAt IS NULL')
            ->andWhere('v.voitureStatus IN (:syncable)')
            ->setParameter('syncable', self::TEMPORAL_SYNCABLE);

        if ($bureauId !== null) {
            $qb->andWhere('v.bureau = :bureau')->setParameter('bureau', $bureauId);
        }

        /** @var Voiture[] $cars */
        $cars    = $qb->getQuery()->getResult();
        $changed = 0;

        foreach ($cars as $car) {
            try {
                $event  = new TemporalSyncTriggered($car->getId(), $bureauId);
                $result = $this->applyEvent($car, $event);
                if ($result->stateChanged) {
                    $changed++;
                }
            } catch (InvalidTransitionException $e) {
                // Skip vehicles whose current DB state has no valid transition path;
                // log and continue so one bad record doesn't abort the whole batch.
                error_log(sprintf('[fleet:sync] Skipped vehicle #%d: %s', $car->getId(), $e->getMessage()));
            }
        }

        return $changed;
    }

    // =========================================================================
    // GUARDS — call these before persisting domain entities
    // =========================================================================

    public function assertCanCreateReservation(
        Voiture $voiture,
        \DateTimeImmutable $dateDebut,
        \DateTimeImmutable $dateFin,
    ): void {
        $state    = $this->resolveState($voiture);
        $snapshot = $this->buildSnapshot($voiture);

        if (!in_array($state, [VehicleLifecycleState::AVAILABLE, VehicleLifecycleState::RESERVED], true)) {
            throw new LifecycleViolationException(
                sprintf('Vehicle is not available for booking (current state: %s)', $state->value),
                'A.1.R1', $voiture->getId(), $state,
                ['state' => $state->value],
            );
        }

        if ($snapshot->complianceOverall === ComplianceService::EXPIRED) {
            throw new LifecycleViolationException(
                'Vehicle has expired compliance documents — booking blocked',
                'A.1.R2', $voiture->getId(), $state,
                ['compliance' => $snapshot->complianceOverall],
            );
        }

        if (!$voiture->getImmatriculation()) {
            throw new LifecycleViolationException(
                'Vehicle has no registration plate — cannot be rented',
                'A.1.R5', $voiture->getId(), $state,
            );
        }

        // Check for conflicting repairs during the period
        $conflictingRepair = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateDebut <= :dateFin')
            ->andWhere('d.dateFin IS NULL OR d.dateFin >= :dateDebut')
            ->setParameter('voiture', $voiture)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()->getSingleScalarResult();

        if ((int) $conflictingRepair > 0) {
            throw new LifecycleViolationException(
                'Vehicle has a scheduled repair during this period',
                'A.1.R3', $voiture->getId(), $state,
                ['dateDebut' => $dateDebut->format('Y-m-d'), 'dateFin' => $dateFin->format('Y-m-d')],
            );
        }

        // Check for overlapping reservations
        $overlap = $this->em->createQueryBuilder()
            ->select('COUNT(res.id)')
            ->from(Reservation::class, 'res')
            ->where('res.voiture = :voiture')
            ->andWhere('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut < :dateFin')
            ->andWhere('res.dateFin > :dateDebut')
            ->setParameter('voiture', $voiture)
            ->setParameter('cancelled', self::CANCELLED_STATUSES)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()->getSingleScalarResult();

        if ((int) $overlap > 0) {
            throw new LifecycleViolationException(
                'Vehicle is already booked for this period',
                'A.1.R4', $voiture->getId(), $state,
                ['dateDebut' => $dateDebut->format('Y-m-d'), 'dateFin' => $dateFin->format('Y-m-d')],
            );
        }
    }

    public function assertCanStartRental(Voiture $voiture, Reservation $reservation): void
    {
        $state    = $this->resolveState($voiture);
        $snapshot = $this->buildSnapshot($voiture);

        if (!in_array($state, [VehicleLifecycleState::AVAILABLE, VehicleLifecycleState::RESERVED], true)) {
            throw new LifecycleViolationException(
                sprintf('Cannot start rental — vehicle state is %s', $state->value),
                'A.2.R1', $voiture->getId(), $state,
            );
        }

        if ($snapshot->complianceOverall === ComplianceService::EXPIRED) {
            throw new LifecycleViolationException(
                'Cannot start rental — vehicle has expired compliance documents',
                'A.2.R2', $voiture->getId(), $state,
            );
        }

        if ($snapshot->hasActiveRepairToday) {
            throw new LifecycleViolationException(
                'Cannot start rental — vehicle has an active repair today',
                'A.2.R3', $voiture->getId(), $state,
            );
        }
    }

    public function assertCanCreateRepair(Voiture $voiture): void
    {
        $state = $this->resolveState($voiture);

        if (in_array($state, [VehicleLifecycleState::SOLD, VehicleLifecycleState::ARCHIVED], true)) {
            throw new LifecycleViolationException(
                'Cannot create repair for a sold or archived vehicle',
                'C.1.R1', $voiture->getId(), $state,
            );
        }
    }

    public function assertCanCreateAssurance(
        Voiture $voiture,
        \DateTimeImmutable $dateDebut,
        \DateTimeImmutable $dateFin,
    ): void {
        $state = $this->resolveState($voiture);

        if (in_array($state, [VehicleLifecycleState::SOLD, VehicleLifecycleState::ARCHIVED], true)) {
            throw new LifecycleViolationException(
                'Cannot add insurance to a sold or archived vehicle',
                'B.1.R1', $voiture->getId(), $state,
            );
        }

        if ($dateFin <= $dateDebut) {
            throw new LifecycleViolationException(
                'Insurance end date must be after start date',
                'B.1.R3', $voiture->getId(), $state,
                ['dateDebut' => $dateDebut->format('Y-m-d'), 'dateFin' => $dateFin->format('Y-m-d')],
            );
        }

        /** @var AssuranceRepository $assuranceRepo */
        $assuranceRepo = $this->em->getRepository(Assurance::class);
        if ($assuranceRepo->hasOverlap($voiture, $dateDebut, $dateFin)) {
            throw new LifecycleViolationException(
                'Insurance policy dates overlap with an existing active policy',
                'B.1.R2', $voiture->getId(), $state,
                ['dateDebut' => $dateDebut->format('Y-m-d'), 'dateFin' => $dateFin->format('Y-m-d')],
            );
        }
    }

    public function assertCanCreateVignette(Voiture $voiture, int $annee): void
    {
        $state = $this->resolveState($voiture);

        if (in_array($state, [VehicleLifecycleState::SOLD, VehicleLifecycleState::ARCHIVED], true)) {
            throw new LifecycleViolationException(
                'Cannot add vignette to a sold or archived vehicle',
                'B.2.R1', $voiture->getId(), $state,
            );
        }

        $existing = $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vignette::class, 'v')
            ->join('v.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('v.annee = :annee')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('voiture', $voiture)
            ->setParameter('annee', $annee)
            ->getQuery()->getSingleScalarResult();

        if ((int) $existing > 0) {
            throw new LifecycleViolationException(
                sprintf('A vignette already exists for year %d for this vehicle', $annee),
                'B.2.R2', $voiture->getId(), $state,
                ['annee' => $annee],
            );
        }
    }

    public function assertCanCreateInspection(Voiture $voiture, bool $depenseLinked): void
    {
        $state = $this->resolveState($voiture);

        if (in_array($state, [VehicleLifecycleState::SOLD, VehicleLifecycleState::ARCHIVED], true)) {
            throw new LifecycleViolationException(
                'Cannot add inspection to a sold or archived vehicle',
                'B.3.R1', $voiture->getId(), $state,
            );
        }

        if (!$depenseLinked) {
            throw new LifecycleViolationException(
                'Technical inspection must be linked to an expense record',
                'B.3.R3', $voiture->getId(), $state,
            );
        }
    }

    public function assertCanInitiateDecommission(Voiture $voiture): void
    {
        $state = $this->resolveState($voiture);

        if (!$state->allowsDecommission()) {
            throw new LifecycleViolationException(
                sprintf('Cannot initiate sale — vehicle state is %s. Only available, blocked, or maintenance vehicles can be decommissioned.', $state->value),
                'D.1.R1', $voiture->getId(), $state,
            );
        }

        // Check for future reservations
        $futureReservations = $this->em->createQueryBuilder()
            ->select('COUNT(res.id)')
            ->from(Reservation::class, 'res')
            ->where('res.voiture = :voiture')
            ->andWhere('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('cancelled', self::CANCELLED_STATUSES)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        if ((int) $futureReservations > 0) {
            throw new LifecycleViolationException(
                sprintf('Vehicle has %d future reservation(s) — cancel them before initiating decommission', $futureReservations),
                'D.1.R2', $voiture->getId(), $state,
                ['futureReservations' => (int) $futureReservations],
            );
        }

        $openRepairs = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        if ((int) $openRepairs > 0) {
            throw new LifecycleViolationException(
                sprintf('Vehicle has %d open repair(s) — close them before initiating decommission', $openRepairs),
                'D.1.R3', $voiture->getId(), $state,
                ['openRepairs' => (int) $openRepairs],
            );
        }
    }

    public function assertCanCompleteSale(Voiture $voiture, float $prixVente): void
    {
        $state = $this->resolveState($voiture);

        if ($state !== VehicleLifecycleState::DECOMMISSIONED) {
            throw new LifecycleViolationException(
                'Vehicle must be in decommission state before sale can be completed',
                'D.2.R1', $voiture->getId(), $state,
            );
        }

        if ($prixVente <= 0) {
            throw new LifecycleViolationException(
                'Sale price must be greater than zero',
                'D.2.R3', $voiture->getId(), $state,
                ['prixVente' => $prixVente],
            );
        }

        $openRepairs = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        if ((int) $openRepairs > 0) {
            throw new LifecycleViolationException(
                sprintf('Vehicle has %d open repair(s) — close them before completing sale', $openRepairs),
                'D.2.R2', $voiture->getId(), $state,
                ['openRepairs' => (int) $openRepairs],
            );
        }
    }

    public function assertCanArchive(Voiture $voiture): void
    {
        $state    = $this->resolveState($voiture);
        $snapshot = $this->buildSnapshot($voiture);

        if ($state !== VehicleLifecycleState::AVAILABLE) {
            throw new LifecycleViolationException(
                sprintf('Only available vehicles can be archived (current state: %s)', $state->value),
                'E.1.R1', $voiture->getId(), $state,
            );
        }

        if ($snapshot->futurePendingReservationCount > 0) {
            throw new LifecycleViolationException(
                'Vehicle has future reservations — cancel them before archiving',
                'E.1.R2', $voiture->getId(), $state,
            );
        }

        if ($snapshot->openRepairCount > 0) {
            throw new LifecycleViolationException(
                'Vehicle has open repairs — close them before archiving',
                'E.1.R3', $voiture->getId(), $state,
            );
        }
    }

    // =========================================================================
    // STATE RESOLUTION — PRIORITY-ORDERED RULE ENGINE
    // =========================================================================

    private function resolveEventMandatedState(FleetEvent $event): ?VehicleLifecycleState
    {
        return match(true) {
            $event instanceof DecommissionInitiated                               => VehicleLifecycleState::DECOMMISSIONED,
            $event instanceof VehicleSold                                         => VehicleLifecycleState::SOLD,
            $event instanceof VehicleArchived                                     => VehicleLifecycleState::ARCHIVED,
            $event instanceof ReturnInspectionCompleted && $event->hasDamage()   => VehicleLifecycleState::MAINTENANCE,
            default                                                               => null,
        };
    }

    private function resolveStateFromSnapshot(VehicleSnapshot $s): VehicleLifecycleState
    {
        // TIER 0 — Immutable manual locks
        if ($s->hasManualLock()) {
            return $s->resolvedManualLock();
        }

        // TIER 1 — Acquisition gates
        if ($s->isDraft()) {
            return VehicleLifecycleState::DRAFT;
        }

        if ($s->isSetupIncomplete()) {
            return VehicleLifecycleState::SETUP;
        }

        // TIER 2 — Blocking states
        if ($s->isComplianceBlocking && !$s->hasActiveReservationToday) {
            return VehicleLifecycleState::BLOCKED;
        }

        // Compliance expired mid-rental: protect active rental, block after return
        if ($s->isComplianceBlocking && $s->hasActiveReservationToday) {
            return VehicleLifecycleState::RENTED; // BLOCKED will apply on ReturnInspectionCompleted
        }

        // Active repair (not currently rented)
        if ($s->hasActiveRepairToday && !$s->hasActiveReservationToday) {
            return VehicleLifecycleState::MAINTENANCE;
        }

        // Open damage records pending repair (not currently rented)
        if ($s->openDamageCount > 0 && !$s->hasActiveReservationToday) {
            return VehicleLifecycleState::MAINTENANCE;
        }

        // TIER 3 — Operational states
        if ($s->hasActiveReservationToday) {
            return VehicleLifecycleState::RENTED;
        }

        if ($s->hasUpcomingReservation24h) {
            return VehicleLifecycleState::RESERVED; // advisory only
        }

        // TIER 4 — Default
        return VehicleLifecycleState::AVAILABLE;
    }

    // =========================================================================
    // SNAPSHOT BUILDER
    // =========================================================================

    private function buildSnapshot(Voiture $voiture): VehicleSnapshot
    {
        $today       = new \DateTimeImmutable('today');
        $tomorrow24h = new \DateTimeImmutable('+24 hours');

        $compliance = $this->complianceService->getComplianceStatus($voiture);
        $oilStatus  = $this->oilChangeService->getOilStatus($voiture);

        // Active repairs today
        $activeRepairRows = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateDebut <= :today')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();

        $activeRepairCount = (int) $activeRepairRows;

        // All open (non-completed, non-deleted) repairs
        $openRepairCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->where('d.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('d.dateFin IS NULL OR d.dateFin > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();

        // Active reservation today
        $activeResCount = (int) $this->em->createQueryBuilder()
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
            ->getQuery()->getSingleScalarResult();

        // Upcoming reservation within 24h
        $upcomingCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(res.id)')
            ->from(Reservation::class, 'res')
            ->where('res.voiture = :voiture')
            ->andWhere('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut > :today')
            ->andWhere('res.dateDebut <= :tomorrow24h')
            ->setParameter('voiture', $voiture)
            ->setParameter('cancelled', self::CANCELLED_STATUSES)
            ->setParameter('today', $today)
            ->setParameter('tomorrow24h', $tomorrow24h)
            ->getQuery()->getSingleScalarResult();

        // Future reservations (for decommission guard)
        $futurePendingCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(res.id)')
            ->from(Reservation::class, 'res')
            ->where('res.voiture = :voiture')
            ->andWhere('res.deletedAt IS NULL')
            ->andWhere('res.reservationStatus NOT IN (:cancelled)')
            ->andWhere('res.dateDebut > :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('cancelled', self::CANCELLED_STATUSES)
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();

        // Open damage records — via return inspection OR direct voiture link (manual damage)
        $dQb = $this->em->createQueryBuilder();
        $openDamageCount = (int) $dQb
            ->select('COUNT(dmg.id)')
            ->from(Damage::class, 'dmg')
            ->leftJoin('dmg.returnInspection', 'ri')
            ->leftJoin('ri.reservation', 'res')
            ->where($dQb->expr()->orX('res.voiture = :voiture', 'dmg.voiture = :voiture'))
            ->andWhere('dmg.status = :open')
            ->setParameter('voiture', $voiture)
            ->setParameter('open', 'open')
            ->getQuery()->getSingleScalarResult();

        // Purchase record exists
        $hasAchat = (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(AchatVoiture::class, 'a')
            ->where('a.voiture = :voiture')
            ->setParameter('voiture', $voiture)
            ->getQuery()->getSingleScalarResult() > 0;

        // Compliance sub-flags
        $hasValidAssurance  = $compliance['assurance']['status'] !== ComplianceService::EXPIRED;
        $hasValidVignette   = $compliance['vignette']['status']  !== ComplianceService::EXPIRED;
        $hasValidInspection = $compliance['visite']['status']    !== ComplianceService::EXPIRED;

        return new VehicleSnapshot(
            voitureId:                    $voiture->getId(),
            storedStatus:                 $voiture->getVoitureStatus() ?? 'brouillon',
            complianceOverall:            $compliance['overall'],
            isComplianceBlocking:         $compliance['overall'] === ComplianceService::EXPIRED,
            hasValidAssurance:            $hasValidAssurance,
            hasValidVignette:             $hasValidVignette,
            hasValidInspection:           $hasValidInspection,
            hasImmatriculation:           !empty($voiture->getImmatriculation()),
            hasKilometrage:               ($voiture->getKilometrageActuel() ?? 0) > 0,
            hasAchat:                     $hasAchat,
            hasActiveRepairToday:         $activeRepairCount > 0,
            activeRepairCount:            $activeRepairCount,
            hasActiveReservationToday:    $activeResCount > 0,
            hasUpcomingReservation24h:    $upcomingCount > 0,
            futurePendingReservationCount: $futurePendingCount,
            openRepairCount:              $openRepairCount,
            oilStatus:                    $oilStatus['status'],
            oilRemainingKm:               $oilStatus['remainingKm'] ?? null,
            openDamageCount:              $openDamageCount,
        );
    }

    // =========================================================================
    // POST-EFFECTS — event-specific side-effects after state transition
    // =========================================================================

    private function runPostEffects(Voiture $voiture, FleetEvent $event, VehicleSnapshot $snapshot): array
    {
        $effects = [];

        // ReturnInspectionCompleted: sync mileage + re-check oil
        if ($event instanceof ReturnInspectionCompleted) {
            $oldKm = $voiture->getKilometrageActuel();
            $voiture->setKilometrageActuel($event->getKilometrage());
            $effects[] = sprintf(
                'Mileage updated: %d → %d km',
                $oldKm,
                $event->getKilometrage(),
            );

            if ($event->hasDamageCharges()) {
                $effects[] = sprintf('Damage charges recorded: %.2f MAD', $event->getDamageCharge());
            }
        }

        // RepairCompleted: close linked damage records
        if ($event instanceof RepairCompleted) {
            $affected = $this->em->createQueryBuilder()
                ->update(Damage::class, 'dmg')
                ->set('dmg.status', ':repaired')
                ->where('dmg.reparationId = :repId')
                ->setParameter('repaired', 'repaired')
                ->setParameter('repId', $event->getReparationId())
                ->getQuery()->execute();

            if ($affected > 0) {
                $effects[] = sprintf('%d damage record(s) marked as repaired', $affected);
            }
        }

        // VehicleSold: archive active insurance records
        if ($event instanceof VehicleSold) {
            $now = new \DateTimeImmutable();
            $affected = $this->em->createQueryBuilder()
                ->update(Assurance::class, 'ass')
                ->set('ass.archivedAt', ':now')
                ->join('ass.depense', 'd')
                ->where('d.voiture = :voiture')
                ->andWhere('ass.archivedAt IS NULL')
                ->andWhere('ass.deletedAt IS NULL')
                ->setParameter('now', $now)
                ->setParameter('voiture', $voiture)
                ->getQuery()->execute();

            if ($affected > 0) {
                $effects[] = sprintf('%d insurance record(s) archived', $affected);
            }
            $effects[] = sprintf('Vehicle sold for %.2f MAD (net: %.2f MAD)', $event->getPrixVente(), $event->getComputedBenefice());
        }

        // VehiclePurchaseRecorded: update voiture.prixAchat for backward compat
        if ($event instanceof VehiclePurchaseRecorded) {
            $voiture->setPrixAchat((string) $event->getPrixAchat());
            $effects[] = 'Purchase price synced to vehicle record';
        }

        return $effects;
    }

    // =========================================================================
    // TRANSITION VALIDATION
    // =========================================================================

    private function assertValidTransition(
        VehicleLifecycleState $from,
        VehicleLifecycleState $to,
        int $voitureId,
    ): void {
        $allowed = self::VALID_TRANSITIONS[$from->value] ?? [];
        if (!in_array($to->value, $allowed, true)) {
            throw new InvalidTransitionException($from, $to, $voitureId);
        }
    }

    // =========================================================================
    // NOTIFICATION DISPATCH
    // =========================================================================

    private function dispatchNotificationForTransition(
        Voiture $voiture,
        VehicleLifecycleState $from,
        VehicleLifecycleState $to,
        FleetEvent $event,
    ): void {
        $plate = $voiture->getImmatriculation() ?? sprintf('#%d', $voiture->getId());

        if ($to === VehicleLifecycleState::BLOCKED) {
            $this->notificationService->createForAllUsers(
                NotificationService::TYPE_COMPLIANCE_EXPIRED,
                'Voiture', $voiture->getId(),
                "Véhicule bloqué — $plate",
                "Le véhicule $plate a un document de conformité expiré et est bloqué.",
                NotificationService::PRIORITY_CRITICAL,
                '/voiture/' . $voiture->getId(),
            );
        } elseif ($to === VehicleLifecycleState::SOLD) {
            $this->notificationService->createForAllUsers(
                NotificationService::TYPE_VEHICLE_SOLD,
                'Voiture', $voiture->getId(),
                "Véhicule vendu — $plate",
                "Le véhicule $plate a été vendu et retiré de la flotte active.",
                NotificationService::PRIORITY_MEDIUM,
                '/voiture/' . $voiture->getId(),
            );
        } elseif ($to === VehicleLifecycleState::AVAILABLE && $from === VehicleLifecycleState::SETUP) {
            $this->notificationService->createForAllUsers(
                NotificationService::TYPE_COMPLIANCE_EXPIRED,
                'Voiture', $voiture->getId(),
                "Véhicule opérationnel — $plate",
                "Le véhicule $plate a terminé la configuration et est prêt à la location.",
                NotificationService::PRIORITY_LOW,
                '/voiture/' . $voiture->getId(),
            );
        }
    }
}
