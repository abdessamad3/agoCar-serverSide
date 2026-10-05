<?php

namespace App\Fleet\Exception;

use App\Enum\VehicleLifecycleState;

/**
 * Thrown when resolveState() produces a state that is not a valid
 * transition from the current stored state.
 * This is a programmer-level error — it means the transition matrix
 * in FleetLifecycleManager::VALID_TRANSITIONS is incomplete.
 */
class InvalidTransitionException extends \LogicException
{
    public function __construct(
        private readonly VehicleLifecycleState $from,
        private readonly VehicleLifecycleState $to,
        private readonly int $voitureId,
    ) {
        parent::__construct(sprintf(
            'Invalid lifecycle transition for vehicle #%d: %s → %s. '
            . 'Add this transition to FleetLifecycleManager::VALID_TRANSITIONS if it is intentional.',
            $voitureId,
            $from->value,
            $to->value,
        ));
    }

    public function getFrom(): VehicleLifecycleState { return $this->from; }
    public function getTo(): VehicleLifecycleState { return $this->to; }
    public function getVoitureId(): int { return $this->voitureId; }
}
