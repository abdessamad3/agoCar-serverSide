<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 * Covers UtilisateurController's three findings from today: no tenant filter
 * at all on list()/show() originally, the god-mode bug (a bureau-less
 * non-admin resolving to "unrestricted" instead of "sees nothing"), and the
 * narrower list-leak where that same misconfigured viewer could still see
 * other bureau-less accounts. Also covers the guard that blocks clearing a
 * non-admin's bureau.
 */
final class UtilisateurControllerTest extends ApiTestCase
{
    public function testStaffCannotSeeAnotherBureausUserInList(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);
        $this->makeUser('staffB@test.local', ['ROLE_STAFF'], $bureauB);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/utilisateur?limit=50');

        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');

        $this->assertContains('staffA@test.local', $emails);
        $this->assertNotContains('staffB@test.local', $emails, 'Staff A must not see Staff B (different bureau) in the user list.');
    }

    public function testStaffCanSeeABureauLessUserInList(): void
    {
        // Yesterday's "Mehdi disappeared from the list" bug: a legitimately
        // bureau-assigned viewer must still see bureau-less accounts.
        $bureauA = $this->makeBureau('Bureau A');
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);
        $this->makeUser('orphan@test.local', ['ROLE_MANAGER'], null);

        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/utilisateur?limit=50');

        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');

        $this->assertContains('orphan@test.local', $emails);
    }

    public function testMisconfiguredViewerSeesNoOneAtAllNotEvenOtherBureauLessUsers(): void
    {
        // The narrower list-leak fix: a bureau-less NON-ADMIN viewer must see
        // nothing, including other bureau-less accounts -- the "stay visible"
        // carve-out is only for a legitimately bureau-assigned viewer.
        $misconfigured = $this->makeUser('misconfigured@test.local', ['ROLE_STAFF'], null);
        $this->makeUser('otherorphan@test.local', ['ROLE_MANAGER'], null);

        $this->httpClient->loginUser($misconfigured);
        $this->httpClient->request('GET', '/api/utilisateur?limit=50');

        $data = json_decode($this->httpClient->getResponse()->getContent(), true);

        $this->assertSame(0, $data['meta']['total'], 'A misconfigured (bureau-less, non-admin) viewer must see zero users, not even other bureau-less ones.');
    }

    public function testTrueAdminSeesEveryone(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);
        $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);
        $this->makeUser('staffB@test.local', ['ROLE_STAFF'], $bureauB);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/utilisateur?limit=50');

        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');

        $this->assertContains('staffA@test.local', $emails);
        $this->assertContains('staffB@test.local', $emails);
    }

    public function testClearingANonAdminsBureauIsRejected(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);
        $staffA  = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request(
            'PUT',
            '/api/utilisateur/' . $staffA->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['bureauId' => null])
        );

        $this->assertSame(422, $this->httpClient->getResponse()->getStatusCode());

        $this->em->refresh($staffA);
        $this->assertNotNull($staffA->getBureau(), 'Rejected request must not have cleared the bureau.');
    }

    public function testClearingAnAdminsBureauIsAllowed(): void
    {
        $bureauA    = $this->makeBureau('Bureau A');
        $admin      = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);
        $adminWithB = $this->makeUser('admin-with-bureau@test.local', ['ROLE_ADMIN'], $bureauA);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request(
            'PUT',
            '/api/utilisateur/' . $adminWithB->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['bureauId' => null])
        );

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }
}
