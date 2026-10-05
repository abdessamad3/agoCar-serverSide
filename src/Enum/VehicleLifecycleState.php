<?php

namespace App\Enum;

/**
 * Single authoritative lifecycle state for a Voiture.
 * String values match the existing voitureStatus column values where they
 * already exist (disponible, louee, maintenance, hors_service, vendu, archive)
 * so no data migration is required for the operational set.
 * New states (brouillon, setup, reserve, decommissioned) are additions.
 */
enum VehicleLifecycleState: string
{
    // ── ACQUISITION ─────────────────────────────────────────────────────────
    case DRAFT          = 'brouillon';      // Entity created, minimum setup not started
    case SETUP          = 'setup';          // Purchase recorded, awaiting compliance docs + mileage

    // ── OPERATIONAL ─────────────────────────────────────────────────────────
    case AVAILABLE      = 'disponible';     // All compliance valid, no active repair
    case RESERVED       = 'reserve';        // Confirmed future rental starts within 24h (advisory)
    case RENTED         = 'louee';          // Active rental in progress

    // ── BLOCKED ─────────────────────────────────────────────────────────────
    case MAINTENANCE    = 'maintenance';    // Active repair — no new rentals
    case BLOCKED        = 'hors_service';   // Compliance expired — legally cannot operate

    // ── TERMINAL ────────────────────────────────────────────────────────────
    case DECOMMISSIONED = 'decommissioned'; // Sale workflow initiated, pending closure
    case SOLD           = 'vendu';          // Final — immutable
    case ARCHIVED       = 'archive';        // Removed from active fleet, not sold

    /** States that are written intentionally and must never be overridden by resolveState(). */
    public function isManualLock(): bool
    {
        return in_array($this, [self::SOLD, self::ARCHIVED, self::DECOMMISSIONED], true);
    }

    /** States in which the vehicle cannot start a new rental. */
    public function blocksRental(): bool
    {
        return in_array($this, [
            self::DRAFT, self::SETUP, self::MAINTENANCE,
            self::BLOCKED, self::DECOMMISSIONED, self::SOLD, self::ARCHIVED,
        ], true);
    }

    /** States from which decommission can be initiated. */
    public function allowsDecommission(): bool
    {
        return in_array($this, [self::AVAILABLE, self::BLOCKED, self::MAINTENANCE], true);
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
