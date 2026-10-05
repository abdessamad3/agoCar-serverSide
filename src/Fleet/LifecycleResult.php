<?php

namespace App\Fleet;

use App\Enum\VehicleLifecycleState;

/**
 * Returned by FleetLifecycleManager::applyEvent().
 * Tells the caller what happened and any advisory warnings.
 */
readonly class LifecycleResult
{
    public function __construct(
        public VehicleLifecycleState $previousState,
        public VehicleLifecycleState $currentState,
        public bool $stateChanged,
        /** Advisory warnings that do not block the operation (oil due, expiring soon, etc.) */
        public array $warnings = [],
        /** Informational messages about post-effects that were applied */
        public array $postEffects = [],
    ) {}

    public static function noChange(VehicleLifecycleState $state): self
    {
        return new self(
            previousState: $state,
            currentState:  $state,
            stateChanged:  false,
        );
    }

    public static function transitioned(
        VehicleLifecycleState $from,
        VehicleLifecycleState $to,
        array $warnings = [],
        array $postEffects = [],
    ): self {
        return new self(
            previousState: $from,
            currentState:  $to,
            stateChanged:  true,
            warnings:      $warnings,
            postEffects:   $postEffects,
        );
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}
