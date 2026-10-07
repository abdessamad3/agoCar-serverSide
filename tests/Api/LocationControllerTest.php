<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the most severe gap found today: LocationController (the whole
 * rental workflow -- hand over keys, close/reopen a contract, record a
 * closing payment) had ZERO bureau awareness at all, not even on list().
 * Any authenticated staff member could drive another bureau's entire
 * rental lifecycle end to end.
 */
final class LocationControllerTest extends ApiTestCase
{
    public function testStaffCannotViewAnotherBureausDossier(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/location/' . $reservationB->getId() . '/full');

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());
    }

    public function testStaffCanViewTheirOwnBureausDossier(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureA = $this->makeVoiture($bureauA, 'A-001');
        $clientA  = $this->makeClientEntity('Client A');
        $reservationA = $this->makeReservation($voitureA, $clientA);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/location/' . $reservationA->getId() . '/full');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testStaffCannotHandOverKeysForAnotherBureausReservation(): void
    {
        // The real-world worst case: driving another bureau's actual rental
        // workflow (handing over a vehicle, starting a contract), not just
        // reading data.
        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request(
            'POST',
            '/api/location/' . $reservationB->getId() . '/remettre-les-cles',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['mileageOut' => 1000])
        );

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());
    }

    public function testTrueAdminCanViewAnyBureausDossier(): void
    {
        $bureauB = $this->makeBureau('Bureau B');
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);

        $voitureB = $this->makeVoiture($bureauB, 'B-001');
        $clientB  = $this->makeClientEntity('Client B');
        $reservationB = $this->makeReservation($voitureB, $clientB);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/location/' . $reservationB->getId() . '/full');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }
}
