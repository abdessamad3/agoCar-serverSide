<?php

namespace App\Tests\Api;

use App\Entity\Adblue;
use App\Entity\Depense;
use App\Enum\StatusEnum;

/**
 * Phase 0, Task 1: AdblueController::show() already had an inline bureau check,
 * but update() and delete() had none at all -- a bureau-locked Manager/Staff user
 * who knew (or guessed) another bureau's AdBlue record id could view, edit, or
 * delete it. This suite proves the fix (assertBureauAccess(), matching the
 * pattern already used by Vidange/Reparation/Vignette/Assurance/SuiviTechnique)
 * closes that gap on all three actions, without regressing legitimate access.
 *
 * One HTTP request per test method -- this suite's loginUser()/stateless-JWT
 * setup doesn't carry authentication across a second request in the same test
 * method (see LocationControllerTest.php's identical note), a pre-existing
 * harness limitation unrelated to this change.
 */
final class AdblueBureauAccessTest extends ApiTestCase
{
    // The base ApiTestCase truncate list doesn't include `adblue` (see the
    // identical workaround in BureauConsumerIsolationTest::setUp()) -- without
    // this, a leftover Adblue row from an earlier test method can reference a
    // depense/voiture id that this test's own truncated tables have since reused.
    protected function setUp(): void
    {
        parent::setUp();
        $this->em->getConnection()->executeStatement('DELETE FROM adblue');
    }

    /** @return array{0: Adblue, 1: Adblue, 2: \App\Entity\Bureau} [ownAdblue (bureau A), foreignAdblue (bureau B), bureauA] */
    private function seedTwoBureaus(): array
    {
        $a = $this->makeBureau('Adblue A');
        $b = $this->makeBureau('Adblue B');
        $va = $this->makeVoiture($a, 'ADB-A');
        $vb = $this->makeVoiture($b, 'ADB-B');

        $ownAdblue = null;
        $foreignAdblue = null;
        foreach ([[$a, $va, true], [$b, $vb, false]] as [$bureau, $voiture, $isOwn]) {
            $depense = new Depense();
            $depense->setBureau($bureau)->setVoiture($voiture)->setDateDebut(new \DateTimeImmutable('today'))
                ->setTypeDepense('adblue')->setMontant('100.00')->setMontantPaye('0')
                ->setStatut(StatusEnum::IMPAYE)->setCreeAu(new \DateTimeImmutable());
            $adblue = new Adblue();
            $adblue->setDepense($depense)->setQuantiteLitre(5)->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($depense);
            $this->em->persist($adblue);
            $this->em->flush();
            if ($isOwn) $ownAdblue = $adblue; else $foreignAdblue = $adblue;
        }

        return [$ownAdblue, $foreignAdblue, $a];
    }

    // ── Cross-bureau: must be blocked ───────────────────────────────────────────

    public function testBureauLockedManagerCannotViewAnotherBureausAdblueRecord(): void
    {
        [, $foreign, $a] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('view-manager@example.test', ['ROLE_MANAGER'], $a));
        $this->httpClient->request('GET', '/api/adblue/' . $foreign->getId());

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());
    }

    public function testBureauLockedManagerCannotUpdateAnotherBureausAdblueRecord(): void
    {
        [, $foreign, $a] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('update-manager@example.test', ['ROLE_MANAGER'], $a));
        $this->httpClient->request(
            'PUT',
            '/api/adblue/' . $foreign->getId(),
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['quantite' => 999])
        );

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());

        // Prove the write was actually blocked, not just the response code.
        $this->em->clear();
        $reloaded = $this->em->getRepository(Adblue::class)->find($foreign->getId());
        self::assertSame(5, $reloaded->getQuantiteLitre(), 'Cross-bureau update must not mutate the foreign record');
    }

    public function testBureauLockedManagerCannotDeleteAnotherBureausAdblueRecord(): void
    {
        [, $foreign, $a] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('delete-manager@example.test', ['ROLE_MANAGER'], $a));
        $this->httpClient->request('DELETE', '/api/adblue/' . $foreign->getId());

        $this->assertForbiddenOrNotFound($this->httpClient->getResponse());

        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $reloaded = $this->em->getRepository(Adblue::class)->find($foreign->getId());
        self::assertNotNull($reloaded, 'Cross-bureau delete must not remove the foreign record');
        self::assertNull($reloaded->getDeletedAt(), 'Cross-bureau delete must not soft-delete the foreign record');
    }

    // ── Same bureau / global admin: must retain full access ────────────────────

    public function testSameBureauManagerCanViewOwnRecord(): void
    {
        [$own, , $a] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('own-view-manager@example.test', ['ROLE_MANAGER'], $a));
        $this->httpClient->request('GET', '/api/adblue/' . $own->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testSameBureauManagerCanUpdateOwnRecord(): void
    {
        [$own, , $a] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('own-update-manager@example.test', ['ROLE_MANAGER'], $a));
        $this->httpClient->request(
            'PUT',
            '/api/adblue/' . $own->getId(),
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['quantite' => 42])
        );

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());

        $this->em->clear();
        $reloaded = $this->em->getRepository(Adblue::class)->find($own->getId());
        self::assertSame(42, $reloaded->getQuantiteLitre(), 'same-bureau update must actually apply');
    }

    public function testSameBureauManagerCanDeleteOwnRecord(): void
    {
        [$own, , $a] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('own-delete-manager@example.test', ['ROLE_MANAGER'], $a));
        $this->httpClient->request('DELETE', '/api/adblue/' . $own->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());

        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $reloaded = $this->em->getRepository(Adblue::class)->find($own->getId());
        self::assertNotNull($reloaded->getDeletedAt(), 'own-bureau delete must soft-delete the record');
    }

    public function testGlobalAdminCanViewAnyBureausRecord(): void
    {
        [, $foreign] = $this->seedTwoBureaus();

        // getEffectiveBureauId() === null for a true admin with no bureau assigned.
        $this->httpClient->loginUser($this->makeUser('global-admin-view@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request('GET', '/api/adblue/' . $foreign->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testGlobalAdminCanUpdateAnyBureausRecord(): void
    {
        [, $foreign] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('global-admin-update@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request(
            'PUT',
            '/api/adblue/' . $foreign->getId(),
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['quantite' => 7])
        );

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());

        $this->em->clear();
        $reloaded = $this->em->getRepository(Adblue::class)->find($foreign->getId());
        self::assertSame(7, $reloaded->getQuantiteLitre(), 'admin update must actually apply');
    }

    public function testGlobalAdminCanDeleteAnyBureausRecord(): void
    {
        [, $foreign] = $this->seedTwoBureaus();

        $this->httpClient->loginUser($this->makeUser('global-admin-delete@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request('DELETE', '/api/adblue/' . $foreign->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());

        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $reloaded = $this->em->getRepository(Adblue::class)->find($foreign->getId());
        self::assertNotNull($reloaded->getDeletedAt(), 'admin delete must soft-delete the record');
    }
}
