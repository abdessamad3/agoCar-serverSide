<?php

namespace App\Fleet;

use App\Enum\VehicleLifecycleState;

/**
 * Immutable data snapshot of everything needed to evaluate lifecycle rules
 * for a single vehicle. Loaded once per resolveState() call — all rules
 * read from this object, no additional queries inside rule evaluation.
 */
readonly class VehicleSnapshot
{
    public function __construct(
        public int $voitureId,

        // Raw stored status before re-resolution
        public string $storedStatus,

        // Compliance
        public string $complianceOverall,   // ComplianceService::VALID|WARNING|CRITICAL|EXPIRED
        public bool $isComplianceBlocking,
        public bool $hasValidAssurance,
        public bool $hasValidVignette,
        public bool $hasValidInspection,    // always true if NOT required

        // Setup gate
        public bool $hasImmatriculation,
        public bool $hasKilometrage,        // kilometrageActuel > 0
        public bool $hasAchat,              // AchatVoiture exists

        // Operational state
        public bool $hasActiveRepairToday,
        public int $activeRepairCount,
        public bool $hasActiveReservationToday,
        public bool $hasUpcomingReservation24h,
        public int $futurePendingReservationCount,
        public int $openRepairCount,

        // Oil change (advisory)
        public string $oilStatus,           // OilChangeService::OK|DUE_SOON|OVERDUE|UNKNOWN
        public ?int $oilRemainingKm,

        // Open damage records from return inspections (not yet repaired)
        public int $openDamageCount = 0,
    ) {}

    /** True if Tier-0 manual lock applies — resolveState() must return immediately. */
    public function hasManualLock(): bool
    {
        $state = VehicleLifecycleState::tryFromString($this->storedStatus);
        return $state !== null && $state->isManualLock();
    }

    public function resolvedManualLock(): ?VehicleLifecycleState
    {
        if (!$this->hasManualLock()) {
            return null;
        }
        return VehicleLifecycleState::from($this->storedStatus);
    }

    /** True if setup gate (S1–S4) is incomplete. */
    public function isSetupIncomplete(): bool
    {
        return !$this->hasImmatriculation
            || !$this->hasKilometrage
            || !$this->hasValidAssurance
            || !$this->hasValidVignette
            || !$this->hasValidInspection;
    }

    /** True if a vehicle was never properly set up (hard DRAFT: no immatriculation + no km + no purchase). */
    public function isDraft(): bool
    {
        return !$this->hasImmatriculation
            && !$this->hasKilometrage
            && !$this->hasAchat;
    }
}
