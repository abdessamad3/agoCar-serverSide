<?php

namespace App\Fleet;

use App\Doctrine\ReservationStatusWriteGuard;
use App\Entity\Reservation;
use App\Fleet\Exception\InvalidReservationTransitionException;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single choke point for all Reservation.reservationStatus changes.
 *
 * Unlike FleetLifecycleManager (Voiture side), this is NOT a snapshot-based
 * rule engine — a reservation's status is an explicit workflow driven by
 * discrete staff/client actions (confirm, hand over keys, return, cancel),
 * not something continuously re-derived from live facts. So this manager
 * only does what controllers were each re-implementing by hand already:
 * validate the transition against a fixed matrix, write the field under the
 * write guard, and log it consistently.
 *
 * RULES:
 *   - Controllers call transition() — never write reservationStatus directly.
 *   - transition() does its own flush(); call it after any related domain
 *     entity (Contrat, VehicleDelivery, VehicleReturnInspection) is already
 *     persisted.
 *   - This does NOT sync Voiture.voitureStatus — callers still dispatch the
 *     appropriate FleetEvent via FleetLifecycleManager::applyEvent() right
 *     after, exactly as before. Keeping that separate lets each call site
 *     attach the right event data (mileage, fuel level, damage...); this
 *     manager only owns the Reservation-side transition itself.
 *   - Creating a new Reservation (first write to reservationStatus, before
 *     the first persist) is NOT affected — the guard only fires on updates
 *     to an already-persisted row.
 */
class ReservationLifecycleManager
{
    /**
     * Permitted status transitions. Key = from, value = array of permitted
     * to-states. A status not listed as a key has no valid outgoing
     * transition (new/unknown legacy value) — attempting to leave it throws.
     */
    private const VALID_TRANSITIONS = [
        'pending'             => ['confirmed', 'en_cours', 'annulee'],
        'confirmed'           => ['en_cours', 'annulee'],
        // Legacy spelling some older rows may still carry — never written
        // going forward, but still a valid starting point to leave from.
        'confirmee'           => ['en_cours', 'annulee'],
        'en_cours'            => ['terminee', 'termine_avant_terme', 'confirmed'],
        'terminee'            => ['en_cours'],
        'termine_avant_terme' => [],
        'annulee'             => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationStatusWriteGuard $guard,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Validate + apply a status transition, flush, and log it. Throws
     * InvalidReservationTransitionException (→ HTTP 422) if the transition
     * isn't in the matrix. A no-op (from === to) is allowed and short-circuits
     * without touching the DB or the log.
     */
    public function transition(Reservation $reservation, string $toStatus, array $logContext = []): void
    {
        $fromStatus = $reservation->getReservationStatus() ?? 'pending';

        if ($fromStatus === $toStatus) {
            return;
        }

        $allowed = self::VALID_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($toStatus, $allowed, true)) {
            throw new InvalidReservationTransitionException($fromStatus, $toStatus, $reservation->getId() ?? 0);
        }

        $this->guard->activate();
        try {
            $reservation->setReservationStatus($toStatus);
            $reservation->setEditAu(new \DateTimeImmutable());
            $this->em->persist($reservation);
            $this->em->flush();
        } finally {
            $this->guard->deactivate();
        }

        $this->activityLog->logUpdate(
            'Reservation',
            $reservation->getId(),
            ['reservationStatus' => $fromStatus],
            array_merge(['reservationStatus' => $toStatus], $logContext),
            $reservation->getBureau(),
        );
    }
}
