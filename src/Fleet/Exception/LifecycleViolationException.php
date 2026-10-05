<?php

namespace App\Fleet\Exception;

use App\Enum\VehicleLifecycleState;

/**
 * Thrown when a pre-action guard fails.
 * Controllers should catch this and return HTTP 422 with the message and context.
 */
class LifecycleViolationException extends \DomainException
{
    public function __construct(
        string $message,
        private readonly string $guardId,
        private readonly int $voitureId,
        private readonly VehicleLifecycleState $currentState,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function getGuardId(): string { return $this->guardId; }
    public function getVoitureId(): int { return $this->voitureId; }
    public function getCurrentState(): VehicleLifecycleState { return $this->currentState; }
    public function getContext(): array { return $this->context; }

    /** Serializes to the array shape expected by API error responses. */
    public function toApiError(): array
    {
        return [
            'error'        => 'lifecycle_violation',
            'guard'        => $this->guardId,
            'message'      => $this->getMessage(),
            'voiture_id'   => $this->voitureId,
            'current_state'=> $this->currentState->value,
            'context'      => $this->context,
        ];
    }
}
