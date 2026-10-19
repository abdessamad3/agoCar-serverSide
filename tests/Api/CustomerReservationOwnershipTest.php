<?php

namespace App\Tests\Api;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.9 Stage C2-C: regression coverage for the explicit
 * Reservation.utilisateur ownership link that replaced the email-equality
 * lookup in PublicClientAuthController::myReservations() (the confirmed
 * cross-account data-isolation defect from Stage C2-A/C2-B1).
 *
 * Each test method issues at most one real HTTP request, per this suite's
 * established JWT-firewall testing constraint (loginUser() only authenticates
 * the immediately-next request). Where a test needs prior state (an existing
 * reservation, a pre-existing account), that state is created directly via
 * the EntityManager rather than through a second HTTP call.
 */
final class CustomerReservationOwnershipTest extends ApiTestCase
{
    private function bookingPayload(int $carId, array $overrides = []): array
    {
        return array_merge([
            'carId'           => $carId,
            'dateDebut'       => (new \DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i'),
            'dateFin'         => (new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i'),
            'fullName'        => 'Jean Testeur',
            'phone'           => '0600000000',
            'email'           => 'guest@test.local',
            'permisDelivreLe' => '2020-01-01',
            'idNumber'        => 'AB123456',
            'idType'          => 'cin',
        ], $overrides);
    }

    // 1. Anonymous guest booking -- preserved exactly, never linked to anyone.
    public function testAnonymousGuestBookingLeavesUtilisateurNull(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-001');

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload($voiture->getId()))
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());

        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['voiture' => $voiture]);
        $this->assertNotNull($reservation);
        $this->assertNull($reservation->getUtilisateur());
    }

    // 2. Authenticated ROLE_CLIENT booking -- linked to that exact account.
    public function testAuthenticatedClientBookingIsLinkedToTheirAccount(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-002');
        $client  = $this->makeUser('client@test.local', ['ROLE_CLIENT'], null);

        $this->httpClient->loginUser($client);
        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload($voiture->getId()))
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());

        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['voiture' => $voiture]);
        $this->assertNotNull($reservation);
        $this->assertNotNull($reservation->getUtilisateur());
        $this->assertSame($client->getId(), $reservation->getUtilisateur()->getId());
    }

    // 3. Invalid/malformed JWT on the nominally-public booking endpoint --
    // documents the actual (Lexik default) behavior rather than assuming it.
    // No loginUser() here: this sends a real Authorization header through the
    // real authenticator, which loginUser()'s simulated auth does not exercise.
    public function testMalformedAuthorizationHeaderOnPublicBookingEndpoint(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-003');

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer not.a.valid.jwt',
            ],
            content: json_encode($this->bookingPayload($voiture->getId()))
        );

        // Confirmed Stage C2-B1 finding: Lexik's default failure handler rejects
        // an invalid bearer token with 401 before the request reaches the
        // controller, even though this route's access_control entry is
        // PUBLIC_ACCESS. This test exists to catch a future change in that
        // behavior, not to assert it is the desired UX.
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->httpClient->getResponse()->getStatusCode());
    }

    // 4. Staff JWT on the public booking endpoint -- booking still succeeds,
    // but must NEVER be attributed to the staff member's own account.
    public function testStaffJwtOnPublicBookingEndpointIsNeverLinkedToStaff(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-004');
        $staff   = $this->makeUser('staff@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload($voiture->getId()))
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());

        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['voiture' => $voiture]);
        $this->assertNotNull($reservation);
        $this->assertNull($reservation->getUtilisateur(), 'A staff account must never be recorded as the customer owner of a public booking.');
    }

    // 5. Cross-account isolation -- account B must never see account A's
    // explicitly-owned reservation.
    public function testCrossAccountReservationIsolation(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-005');
        $clientA = $this->makeUser('clientA@test.local', ['ROLE_CLIENT'], null);
        $clientB = $this->makeUser('clientB@test.local', ['ROLE_CLIENT'], null);
        $entityA = $this->makeClientEntity('Client A');

        $reservation = $this->makeReservation($voiture, $entityA, 'confirmed');
        $reservation->setUtilisateur($clientA);
        $this->em->flush();

        $this->httpClient->loginUser($clientB);
        $this->httpClient->request('GET', '/api/client-portal/my-reservations');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame([], json_decode($this->httpClient->getResponse()->getContent(), true));
    }

    // 6. Guest booking using an already-registered customer's email -- the
    // booking must succeed (unchanged business behavior) but must NOT be
    // auto-linked to that account just because the email matches.
    public function testGuestBookingWithAnotherPersonsEmailIsNotAutoLinked(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-006');
        $owner   = $this->makeUser('owner@test.local', ['ROLE_CLIENT'], null);

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload($voiture->getId(), ['email' => $owner->getEmail()]))
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());

        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['voiture' => $voiture]);
        $this->assertNotNull($reservation);
        $this->assertNull($reservation->getUtilisateur(), 'Email equality must never be treated as proof of ownership.');
    }

    // 7. Historical (pre-fix-shaped) unlinked reservation -- even when the
    // Client row's email happens to equal the logged-in account's email, it
    // must stay invisible, confirming the old email-matching path is gone.
    public function testHistoricalUnlinkedReservationStaysInvisible(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-007');
        $client  = $this->makeUser('historical@test.local', ['ROLE_CLIENT'], null);

        $entity = $this->makeClientEntity('Historical Client');
        $entity->setEmail('historical@test.local'); // deliberately matches the account's email
        $this->em->flush();
        $this->makeReservation($voiture, $entity, 'terminee'); // utilisateur left NULL

        $this->httpClient->loginUser($client);
        $this->httpClient->request('GET', '/api/client-portal/my-reservations');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame([], json_decode($this->httpClient->getResponse()->getContent(), true));
    }

    // 8. Changing a Client's email after the fact must not change anyone's
    // visibility -- ownership no longer depends on Client.email at all.
    public function testChangingClientEmailDoesNotAffectVisibility(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-008');
        $client  = $this->makeUser('realowner@test.local', ['ROLE_CLIENT'], null);

        $entity = $this->makeClientEntity('Some Client');
        $entity->setEmail('unrelated@test.local');
        $this->makeReservation($voiture, $entity, 'confirmed'); // utilisateur NULL, unrelated to $client

        // Now retarget the Client row's email to match $client -- under the old
        // email-matching code this would have made the reservation appear for
        // $client; under the fix it must not.
        $entity->setEmail($client->getEmail());
        $this->em->flush();

        $this->httpClient->loginUser($client);
        $this->httpClient->request('GET', '/api/client-portal/my-reservations');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame([], json_decode($this->httpClient->getResponse()->getContent(), true));
    }

    // 9. Deleting a customer account must succeed (ON DELETE SET NULL), not
    // fail with a foreign-key conflict, and must leave the reservation intact
    // with its ownership link cleared.
    public function testDeletingCustomerAccountSetsOwnershipNullInsteadOfBlocking(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-009');
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], $bureau);
        $client  = $this->makeUser('todelete@test.local', ['ROLE_CLIENT'], null);
        $entity  = $this->makeClientEntity('Client To Delete');

        $reservation = $this->makeReservation($voiture, $entity, 'terminee');
        $reservation->setUtilisateur($client);
        $this->em->flush();
        $reservationId = $reservation->getId();
        $clientId = $client->getId();

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('DELETE', '/api/utilisateur/' . $clientId);

        $this->assertSame(
            Response::HTTP_NO_CONTENT,
            $this->httpClient->getResponse()->getStatusCode(),
            'Deleting a client account must not be blocked by its past reservations: ' . $this->httpClient->getResponse()->getContent()
        );

        $this->em->clear();
        $reservationAfter = $this->em->getRepository(Reservation::class)->find($reservationId);
        $this->assertNotNull($reservationAfter, 'The reservation row itself must survive the account deletion.');
        $this->assertNull($reservationAfter->getUtilisateur(), 'ON DELETE SET NULL must have cleared the ownership link.');
    }

    // 10. A new account registered with a previously-deleted account's email
    // must not inherit that old account's reservation history.
    public function testEmailReuseAfterAccountDeletionDoesNotInheritHistory(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-010');
        $entity  = $this->makeClientEntity('Original Owner');
        $original = $this->makeUser('reused@test.local', ['ROLE_CLIENT'], null);

        $reservation = $this->makeReservation($voiture, $entity, 'terminee');
        $reservation->setUtilisateur($original);
        $this->em->flush();

        // Hard-delete the original account directly (equivalent end state to
        // test 9's HTTP path, without spending this test's one request on it)
        // and register a brand-new account that happens to reuse the same
        // now-available email.
        $this->em->remove($original);
        $this->em->flush();
        // Detach everything so the already-loaded $reservation object (still
        // holding an in-memory reference to the now-removed $original) isn't
        // re-inspected as a stale association on the next flush below.
        $this->em->clear();
        $newOwner = $this->makeUser('reused@test.local', ['ROLE_CLIENT'], null);

        $this->httpClient->loginUser($newOwner);
        $this->httpClient->request('GET', '/api/client-portal/my-reservations');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame([], json_decode($this->httpClient->getResponse()->getContent(), true));
    }

    // 11. The portal's JSON response contract is unchanged -- only which rows
    // are selected changed, never their shape.
    public function testPortalResponseContractUnchanged(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-011');
        $client  = $this->makeUser('contract@test.local', ['ROLE_CLIENT'], null);
        $entity  = $this->makeClientEntity('Contract Client');

        $reservation = $this->makeReservation($voiture, $entity, 'confirmed');
        $reservation->setUtilisateur($client);
        $reservation->setLieuLivraison('Casablanca');
        $this->em->flush();

        $this->httpClient->loginUser($client);
        $this->httpClient->request('GET', '/api/client-portal/my-reservations');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $row = $data[0];
        foreach (['id', 'ref', 'status', 'dateDebut', 'dateFin', 'total', 'lieuLivraison', 'voiture', 'creeAu'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
        $this->assertSame('Casablanca', $row['lieuLivraison']);
        $this->assertSame($reservation->getId(), $row['id']);
    }

    // 12. Existing admin-side reservation listing is unaffected by the new
    // nullable column/association.
    public function testAdminReservationListingUnaffectedByOwnershipColumn(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'A-012');
        $admin   = $this->makeUser('admin2@test.local', ['ROLE_ADMIN'], $bureau);
        $entity  = $this->makeClientEntity('Admin View Client');
        $this->makeReservation($voiture, $entity, 'confirmed');

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/reservation');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }
}
