<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the gap found and fixed in production today: show/update/delete on
 * a single reservation had zero bureau scoping, so any authenticated staff
 * member could reach another bureau's reservation (full client PII, driver
 * details, financials) just by knowing/guessing its ID.
 */
final class ReservationControllerTest extends ApiTestCase
{
    public function testStaffCannotViewAnotherBureausReservation(): void
    {
        

        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/reservation/' . $reservationB->getId());

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());
    }

    public function testStaffCanViewTheirOwnBureausReservation(): void
    {
        

        $bureauA = $this->makeBureau('Bureau A');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureA = $this->makeVoiture($bureauA, 'A-001');
        $clientA  = $this->makeClientEntity('Client A');
        $reservationA = $this->makeReservation($voitureA, $clientA);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/reservation/' . $reservationA->getId());

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testStaffCannotUpdateAnotherBureausReservation(): void
    {
        

        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request(
            'PUT',
            '/api/reservation/' . $reservationB->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['total' => '1.00'])
        );

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());
    }

    public function testStaffCannotDeleteAnotherBureausReservation(): void
    {
        

        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('DELETE', '/api/reservation/' . $reservationB->getId());

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());
    }

    public function testTrueAdminCanViewAnyBureausReservation(): void
    {


        $bureauB = $this->makeBureau('Bureau B');
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/reservation/' . $reservationB->getId());

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    /**
     * The freeform hole found today: update() wrote reservationStatus from
     * the request body with zero validation, so a client could PUT
     * {"reservationStatus": "terminee"} on a still-pending reservation and
     * skip the entire rental workflow (hand-over, return inspection,
     * compliance checks) entirely. Now routed through
     * ReservationLifecycleManager, which must reject it.
     */
    public function testUpdateRejectsAStatusJumpThatSkipsTheWorkflow(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureA = $this->makeVoiture($bureauA, 'A-001');
        $clientA  = $this->makeClientEntity('Client A');
        $reservationA = $this->makeReservation($voitureA, $clientA, status: 'pending');

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request(
            'PUT',
            '/api/reservation/' . $reservationA->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservationStatus' => 'terminee'])
        );

        $this->assertSame(422, $this->httpClient->getResponse()->getStatusCode());

        $this->em->refresh($reservationA);
        $this->assertSame('pending', $reservationA->getReservationStatus(), 'Rejected transition must not have written the new status.');
    }

    public function testUpdateAllowsTheRealConfirmationFlow(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureA = $this->makeVoiture($bureauA, 'A-001');
        $clientA  = $this->makeClientEntity('Client A');
        $reservationA = $this->makeReservation($voitureA, $clientA, status: 'pending');

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request(
            'PUT',
            '/api/reservation/' . $reservationA->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservationStatus' => 'confirmed'])
        );

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());

        $this->em->refresh($reservationA);
        $this->assertSame('confirmed', $reservationA->getReservationStatus());
    }
}
