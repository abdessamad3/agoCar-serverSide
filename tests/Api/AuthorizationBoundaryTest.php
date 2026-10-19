<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.1 regression coverage: a bare self-registered account (stored
 * roles = ['ROLE_USER'], the only role AuthController::register() ever
 * assigns) must not reach any admin/staff API surface, while every
 * legitimately provisioned staff role keeps the access it had before the
 * access_control fix in security.yaml.
 */
final class AuthorizationBoundaryTest extends ApiTestCase
{
    public function testAnonymousCannotAccessVehicleList(): void
    {
        // This app's exception listener returns 403 (not 401) for an
        // anonymous request against a role-gated access_control entry --
        // pre-existing convention, unrelated to this fix.
        $this->httpClient->request('GET', '/api/voiture');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testAnonymousCannotAccessUserManagement(): void
    {
        $this->httpClient->request('GET', '/api/utilisateur');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testBareRoleUserCannotListVehicles(): void
    {
        // What AuthController::register() actually produces for anyone who
        // hits the public /api/auth/register endpoint.
        $selfRegistered = $this->makeUser('selfregistered@test.local', ['ROLE_USER'], null);

        $this->httpClient->loginUser($selfRegistered);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testBareRoleUserCannotListClients(): void
    {
        $selfRegistered = $this->makeUser('selfregistered2@test.local', ['ROLE_USER'], null);

        $this->httpClient->loginUser($selfRegistered);
        $this->httpClient->request('GET', '/api/client');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testBareRoleUserCannotListStaffUsers(): void
    {
        // The adjacent gap found during Phase 11/12.1 review: UtilisateurController::list()
        // has no #[IsGranted] of its own and relied entirely on the access_control catch-all.
        $selfRegistered = $this->makeUser('selfregistered3@test.local', ['ROLE_USER'], null);

        $this->httpClient->loginUser($selfRegistered);
        $this->httpClient->request('GET', '/api/utilisateur');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testBareRoleUserCannotCreateAReservation(): void
    {
        // The access_control gate must deny this before the controller ever
        // runs, so an invalid/incomplete payload is fine here -- no voiture
        // or client fixtures needed (and none are created: a denied request
        // must not touch the database at all).
        $selfRegistered = $this->makeUser('selfregistered4@test.local', ['ROLE_USER'], null);

        $this->httpClient->loginUser($selfRegistered);
        $this->httpClient->request(
            'POST',
            '/api/reservation',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['voitureId' => 999999])
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testBareRoleUserCanStillReadOwnProfile(): void
    {
        // /api/auth/me is intentionally left on bare ROLE_USER -- it only ever
        // returns the caller's own data, so it stays harmless and unauthenticated
        // self-registration must still resolve to *something* after login.
        $selfRegistered = $this->makeUser('selfregistered5@test.local', ['ROLE_USER'], null);

        $this->httpClient->loginUser($selfRegistered);
        $this->httpClient->request('GET', '/api/auth/me');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testRoleStaffRetainsVehicleListAccess(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testRoleManagerRetainsVehicleListAccess(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $manager = $this->makeUser('manager@test.local', ['ROLE_MANAGER'], $bureau);

        $this->httpClient->loginUser($manager);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    // Deployment-prep gap check (AGOCAR 2.0 release audit): the catch-all
    // access_control whitelist also names ROLE_ACCOUNTANT and ROLE_MECHANIC,
    // but neither had any test coverage anywhere in this suite before now.
    public function testRoleAccountantRetainsVehicleListAccess(): void
    {
        $bureau     = $this->makeBureau('Bureau A');
        $accountant = $this->makeUser('accountant@test.local', ['ROLE_ACCOUNTANT'], $bureau);

        $this->httpClient->loginUser($accountant);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testRoleMechanicRetainsVehicleListAccess(): void
    {
        $bureau   = $this->makeBureau('Bureau A');
        $mechanic = $this->makeUser('mechanic@test.local', ['ROLE_MECHANIC'], $bureau);

        $this->httpClient->loginUser($mechanic);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    // NOTE: one HTTP request per test method, matching every other test in
    // this suite (ApiTestCase/UtilisateurControllerTest etc). The `api`
    // firewall is stateless (JWT) -- confirmed empirically that a second
    // request in the same test method returns 403 even immediately after a
    // fresh loginUser() call, regardless of role or endpoint. This is a
    // pre-existing KernelBrowser/stateless-firewall test-client limitation,
    // unrelated to this fix.

    public function testRoleAdminRetainsUserManagementAccess(): void
    {
        $admin = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/utilisateur');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testRoleAdminRetainsVehicleListAccess(): void
    {
        $admin = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testRoleStaffCannotPerformAdminOnlyVehicleDelete(): void
    {
        // Confirms the fix didn't accidentally widen anything: ROLE_STAFF now
        // clears the firewall-level gate, but controller-level #[IsGranted('ROLE_ADMIN')]
        // on admin-only actions must still block it.
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff2@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'TEST-0002');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/voiture/' . $voiture->getId());

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testClientPortalAccountStillCannotReachAdminApi(): void
    {
        // Pre-existing behavior (ROLE_CLIENT never gets the auto-appended
        // ROLE_USER) -- must remain unaffected by this change.
        $client = $this->makeUser('client@test.local', ['ROLE_CLIENT'], null);

        $this->httpClient->loginUser($client);
        $this->httpClient->request('GET', '/api/voiture');

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }
}
