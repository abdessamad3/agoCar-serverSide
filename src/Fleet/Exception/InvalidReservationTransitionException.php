<?php

namespace App\Fleet\Exception;

/**
 * Thrown when ReservationLifecycleManager::transition() is asked to move a
 * reservation to a status that isn't reachable from its current status.
 * Unlike InvalidTransitionException (Voiture side), this is a routine,
 * user-facing validation failure — not a programmer error — so it maps to
 * HTTP 422, not 500. See LifecycleExceptionSubscriber.
 */
class InvalidReservationTransitionException extends \DomainException
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
        private readonly int $reservationId,
    ) {
        parent::__construct(sprintf(
            'Invalid reservation transition for reservation #%d: %s → %s.',
            $reservationId,
            $from,
            $to,
        ));
    }

    public function getFrom(): string { return $this->from; }
    public function getTo(): string { return $this->to; }
    public function getReservationId(): int { return $this->reservationId; }

    public function toApiError(): array
    {
        return [
            'error'          => 'invalid_reservation_transition',
            'message'        => sprintf(
                'Impossible de passer la réservation de "%s" à "%s".',
                $this->from,
                $this->to,
            ),
            'reservation_id' => $this->reservationId,
            'from'           => $this->from,
            'to'             => $this->to,
        ];
    }
}
