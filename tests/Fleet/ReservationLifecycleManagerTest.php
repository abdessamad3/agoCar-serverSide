<?php

namespace App\Tests\Fleet;

use App\Fleet\Exception\InvalidReservationTransitionException;
use App\Fleet\ReservationLifecycleManager;
use App\Tests\Api\ApiTestCase;

/**
 * Covers today's ReservationLifecycleManager introduction: the matrix must
 * reject a transition that skips the real rental workflow (e.g. a direct
 * pending -> terminee jump, the freeform hole ReservationController::update()
 * used to allow), while still permitting every transition the live
 * controllers actually perform.
 */
final class ReservationLifecycleManagerTest extends ApiTestCase
{
    private function manager(): ReservationLifecycleManager
    {
        return static::getContainer()->get(ReservationLifecycleManager::class);
    }

    public function testValidTransitionSucceeds(): void
    {
        $bureau      = $this->makeBureau('Bureau A');
        $voiture     = $this->makeVoiture($bureau, 'A-001');
        $client      = $this->makeClientEntity('Client A');
        $reservation = $this->makeReservation($voiture, $client); // starts 'confirmed'

        $this->manager()->transition($reservation, 'en_cours');

        $this->assertSame('en_cours', $reservation->getReservationStatus());
    }

    public function testInvalidTransitionIsRejected(): void
    {
        $bureau      = $this->makeBureau('Bureau A');
        $voiture     = $this->makeVoiture($bureau, 'A-001');
        $client      = $this->makeClientEntity('Client A');
        $reservation = $this->makeReservation($voiture, $client, status: 'pending');

        // pending -> terminee skips the entire rental workflow and must be rejected.
        $this->expectException(InvalidReservationTransitionException::class);
        $this->manager()->transition($reservation, 'terminee');
    }

    public function testSameStatusIsANoOp(): void
    {
        $bureau      = $this->makeBureau('Bureau A');
        $voiture     = $this->makeVoiture($bureau, 'A-001');
        $client      = $this->makeClientEntity('Client A');
        $reservation = $this->makeReservation($voiture, $client); // 'confirmed'

        // Must not throw even though 'confirmed' has no self-transition listed.
        $this->manager()->transition($reservation, 'confirmed');

        $this->assertSame('confirmed', $reservation->getReservationStatus());
    }

    public function testTerminalStatusHasNoWayOut(): void
    {
        $bureau      = $this->makeBureau('Bureau A');
        $voiture     = $this->makeVoiture($bureau, 'A-001');
        $client      = $this->makeClientEntity('Client A');
        $reservation = $this->makeReservation($voiture, $client, status: 'annulee');

        $this->expectException(InvalidReservationTransitionException::class);
        $this->manager()->transition($reservation, 'confirmed');
    }
}
