<?php

namespace App\Tests\Api;

use App\Entity\Adblue;
use App\Entity\Assurance;
use App\Entity\Depense;
use App\Enum\StatusEnum;

/**
 * Final security correction before release: BureauAwareTrait::getEffectiveBureauId()
 * (already committed, already live) returns 0 -- not null -- for a non-admin user
 * with no bureau assigned, specifically so every bureau check in the app resolves
 * to "sees/can-touch nothing" for that misconfigured case. AdblueController::list(),
 * AssuranceController::list(), and DepenseController::list() used a plain truthy
 * check (`if ($bureauId)`), which treats 0 the same as null -- "no filter, see
 * everything" -- silently granting a misconfigured account the same visibility as
 * a true global admin. DepenseController::create() didn't use
 * getEffectiveBureauId() at all: a bureau-less caller of ANY role could freely pick
 * the target bureau via request data, with no role check whatsoever.
 *
 * One HTTP request per test method (same harness constraint as every other suite
 * this engagement added -- see LocationControllerTest.php's original note).
 */
final class BureauZeroSentinelSecurityTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // adblue isn't in the base truncate list (see AdblueBureauAccessTest's
        // identical note) -- this suite seeds it directly.
        $this->em->getConnection()->executeStatement('DELETE FROM adblue');
    }

    private function seedAdblueIn(\App\Entity\Bureau $bureau, \App\Entity\Voiture $voiture): Adblue
    {
        $depense = new Depense();
        $depense->setBureau($bureau)->setVoiture($voiture)->setDateDebut(new \DateTimeImmutable('today'))
            ->setTypeDepense('adblue')->setMontant('50.00')->setMontantPaye('0')
            ->setStatut(StatusEnum::IMPAYE)->setCreeAu(new \DateTimeImmutable());
        $adblue = new Adblue();
        $adblue->setDepense($depense)->setQuantiteLitre(5)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->persist($adblue);
        $this->em->flush();
        return $adblue;
    }

    private function seedAssuranceIn(\App\Entity\Bureau $bureau, \App\Entity\Voiture $voiture): Assurance
    {
        $depense = new Depense();
        $depense->setBureau($bureau)->setVoiture($voiture)->setDateDebut(new \DateTimeImmutable('today'))
            ->setDateFin(new \DateTimeImmutable('+1 year'))
            ->setTypeDepense('assurance')->setMontant('500.00')->setMontantPaye('0')
            ->setStatut(StatusEnum::IMPAYE)->setCreeAu(new \DateTimeImmutable());
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Test Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->persist($assurance);
        $this->em->flush();
        return $assurance;
    }

    private function seedStandaloneDepenseIn(\App\Entity\Bureau $bureau): Depense
    {
        $depense = new Depense();
        $depense->setBureau($bureau)->setDateDebut(new \DateTimeImmutable('today'))
            ->setTypeDepense('loyer')->setMontant('300.00')->setMontantPaye('0')
            ->setStatut(StatusEnum::IMPAYE)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->flush();
        return $depense;
    }

    // ── AdblueController::list() ────────────────────────────────────────────

    public function testAdblueListGlobalAdminSeesAllBureaus(): void
    {
        $a = $this->makeBureau('Adb Sentinel A');
        $b = $this->makeBureau('Adb Sentinel B');
        $this->seedAdblueIn($a, $this->makeVoiture($a, 'ADS-A'));
        $this->seedAdblueIn($b, $this->makeVoiture($b, 'ADS-B'));

        $this->httpClient->loginUser($this->makeUser('adb-admin@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request('GET', '/api/adblue');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(2, $body['data'], 'global admin must see records from every bureau');
    }

    public function testAdblueListStaffBureauASeesOnlyBureauA(): void
    {
        $a = $this->makeBureau('Adb Sentinel A2');
        $b = $this->makeBureau('Adb Sentinel B2');
        $this->seedAdblueIn($a, $this->makeVoiture($a, 'ADS-A2'));
        $this->seedAdblueIn($b, $this->makeVoiture($b, 'ADS-B2'));

        $this->httpClient->loginUser($this->makeUser('adb-staffA@example.test', ['ROLE_STAFF'], $a));
        $this->httpClient->request('GET', '/api/adblue');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(1, $body['data'], 'bureau-A staff must see only bureau A records');
    }

    public function testAdblueListStaffBureauBSeesOnlyBureauB(): void
    {
        $a = $this->makeBureau('Adb Sentinel A3');
        $b = $this->makeBureau('Adb Sentinel B3');
        $this->seedAdblueIn($a, $this->makeVoiture($a, 'ADS-A3'));
        $this->seedAdblueIn($b, $this->makeVoiture($b, 'ADS-B3'));

        $this->httpClient->loginUser($this->makeUser('adb-staffB@example.test', ['ROLE_STAFF'], $b));
        $this->httpClient->request('GET', '/api/adblue');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(1, $body['data'], 'bureau-B staff must see only bureau B records');
    }

    public function testAdblueListStaffWithNoBureauSeesNothing(): void
    {
        $a = $this->makeBureau('Adb Sentinel A4');
        $b = $this->makeBureau('Adb Sentinel B4');
        $this->seedAdblueIn($a, $this->makeVoiture($a, 'ADS-A4'));
        $this->seedAdblueIn($b, $this->makeVoiture($b, 'ADS-B4'));

        // Misconfigured account: a staff role with no bureau assigned at all.
        $this->httpClient->loginUser($this->makeUser('adb-nobodureau@example.test', ['ROLE_STAFF'], null));
        $this->httpClient->request('GET', '/api/adblue');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(0, $body['data'], 'a bureau-less non-admin must see nothing, not everything');
    }

    // ── AssuranceController::list() ─────────────────────────────────────────

    public function testAssuranceListGlobalAdminSeesAllBureaus(): void
    {
        $a = $this->makeBureau('Ass Sentinel A');
        $b = $this->makeBureau('Ass Sentinel B');
        $this->seedAssuranceIn($a, $this->makeVoiture($a, 'ASS-A'));
        $this->seedAssuranceIn($b, $this->makeVoiture($b, 'ASS-B'));

        $this->httpClient->loginUser($this->makeUser('ass-admin@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request('GET', '/api/assurance');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(2, $body['data']);
    }

    public function testAssuranceListStaffWithNoBureauSeesNothing(): void
    {
        $a = $this->makeBureau('Ass Sentinel A2');
        $b = $this->makeBureau('Ass Sentinel B2');
        $this->seedAssuranceIn($a, $this->makeVoiture($a, 'ASS-A2'));
        $this->seedAssuranceIn($b, $this->makeVoiture($b, 'ASS-B2'));

        $this->httpClient->loginUser($this->makeUser('ass-nobureau@example.test', ['ROLE_MANAGER'], null));
        $this->httpClient->request('GET', '/api/assurance');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(0, $body['data'], 'a bureau-less non-admin must see nothing, not everything');
    }

    // ── DepenseController::list() ───────────────────────────────────────────

    public function testDepenseListGlobalAdminSeesAllBureaus(): void
    {
        $a = $this->makeBureau('Dep Sentinel A');
        $b = $this->makeBureau('Dep Sentinel B');
        $this->seedStandaloneDepenseIn($a);
        $this->seedStandaloneDepenseIn($b);

        $this->httpClient->loginUser($this->makeUser('dep-admin@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request('GET', '/api/depense');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(2, $body['data']);
    }

    public function testDepenseListStaffBureauASeesOnlyBureauA(): void
    {
        $a = $this->makeBureau('Dep Sentinel A2');
        $b = $this->makeBureau('Dep Sentinel B2');
        $this->seedStandaloneDepenseIn($a);
        $this->seedStandaloneDepenseIn($b);

        $this->httpClient->loginUser($this->makeUser('dep-staffA@example.test', ['ROLE_STAFF'], $a));
        $this->httpClient->request('GET', '/api/depense');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(1, $body['data']);
    }

    public function testDepenseListStaffWithNoBureauSeesNothing(): void
    {
        $a = $this->makeBureau('Dep Sentinel A3');
        $b = $this->makeBureau('Dep Sentinel B3');
        $this->seedStandaloneDepenseIn($a);
        $this->seedStandaloneDepenseIn($b);

        $this->httpClient->loginUser($this->makeUser('dep-nobureau@example.test', ['ROLE_ACCOUNTANT'], null));
        $this->httpClient->request('GET', '/api/depense');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(0, $body['data'], 'a bureau-less non-admin must see nothing, not everything');
    }

    public function testDepenseListStaffWithNoBureauCannotUseQueryParamToPickAnotherBureau(): void
    {
        $a = $this->makeBureau('Dep Sentinel A4');
        $b = $this->makeBureau('Dep Sentinel B4');
        $this->seedStandaloneDepenseIn($a);
        $this->seedStandaloneDepenseIn($b);

        // A misconfigured account must not be able to "choose" bureau B by
        // simply passing it as a query filter either.
        $this->httpClient->loginUser($this->makeUser('dep-nobureau-query@example.test', ['ROLE_STAFF'], null));
        $this->httpClient->request('GET', '/api/depense?bureauId=' . $b->getId());

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertCount(0, $body['data'], 'a bureau-less non-admin must not be able to pick a bureau via query param');
    }

    // ── DepenseController::create() ─────────────────────────────────────────

    public function testDepenseCreateGlobalAdminCanChooseTargetBureau(): void
    {
        $target = $this->makeBureau('Create Sentinel Target');

        $this->httpClient->loginUser($this->makeUser('create-admin@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request(
            'POST',
            '/api/depense',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['typeDepense' => 'loyer', 'montant' => '100.00', 'bureauId' => $target->getId()])
        );

        self::assertSame(201, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);

        $this->em->clear();
        $created = $this->em->getRepository(Depense::class)->find($body['id']);
        self::assertSame($target->getId(), $created->getBureau()?->getId(), 'admin-chosen target bureau must be honored');
    }

    public function testDepenseCreateStaffWithOwnBureauIgnoresAnyBureauIdInRequest(): void
    {
        $own    = $this->makeBureau('Create Sentinel Own');
        $other  = $this->makeBureau('Create Sentinel Other');

        $this->httpClient->loginUser($this->makeUser('create-staff@example.test', ['ROLE_STAFF'], $own));
        $this->httpClient->request(
            'POST',
            '/api/depense',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['typeDepense' => 'loyer', 'montant' => '100.00', 'bureauId' => $other->getId()])
        );

        self::assertSame(201, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);

        $this->em->clear();
        $created = $this->em->getRepository(Depense::class)->find($body['id']);
        self::assertSame($own->getId(), $created->getBureau()?->getId(), "staff's own bureau must win, regardless of what they pass");
    }

    public function testDepenseCreateGlobalAdminCanCreateBureauLessExpense(): void
    {
        // Preserve: a true admin may still create a bureau-less expense
        // (no bureauId at all) -- unaffected by the new 403 guard below.
        $this->httpClient->loginUser($this->makeUser('create-admin-nobureau@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request(
            'POST',
            '/api/depense',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['typeDepense' => 'loyer', 'montant' => '100.00'])
        );

        self::assertSame(201, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);

        $this->em->clear();
        $created = $this->em->getRepository(Depense::class)->find($body['id']);
        self::assertNull($created->getBureau(), 'admin must still be able to create a bureau-less expense');
    }

    public function testDepenseCreateStaffWithNoBureauIsRejectedWith403BeforePersisting(): void
    {
        $other = $this->makeBureau('Create Sentinel Attacker Target');

        // Misconfigured account: no bureau at all. Per the final decision,
        // this must now be rejected outright -- not silently created with a
        // null bureau -- since nothing authorizes this workflow and the
        // creator could never reach the record again afterward anyway.
        $this->httpClient->loginUser($this->makeUser('create-nobureau@example.test', ['ROLE_STAFF'], null));
        $this->httpClient->request(
            'POST',
            '/api/depense',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['typeDepense' => 'loyer', 'montant' => '100.00', 'bureauId' => $other->getId()])
        );

        $resp = $this->httpClient->getResponse();
        self::assertSame(403, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        self::assertSame('no_bureau_assigned', $body['error']);

        // Nothing must have been persisted at all.
        $countBefore = $this->em->getRepository(Depense::class)->count(['typeDepense' => 'loyer']);
        self::assertSame(0, $countBefore, 'no Depense must be persisted when the request is rejected');
    }

    // ── DepenseController::summary() ────────────────────────────────────────

    private function seedThisMonthStandaloneDepense(\App\Entity\Bureau $bureau, string $montant): Depense
    {
        $depense = new Depense();
        $depense->setBureau($bureau)->setDateDebut(new \DateTimeImmutable('today'))
            ->setTypeDepense('loyer')->setMontant($montant)->setMontantPaye('0')
            ->setStatut(StatusEnum::PENDING)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->flush();
        return $depense;
    }

    public function testSummaryGlobalAdminSeesCombinedTotalAcrossBureaus(): void
    {
        $a = $this->makeBureau('Summary Sentinel A');
        $b = $this->makeBureau('Summary Sentinel B');
        $this->seedThisMonthStandaloneDepense($a, '100.00');
        $this->seedThisMonthStandaloneDepense($b, '250.00');

        $this->httpClient->loginUser($this->makeUser('summary-admin@example.test', ['ROLE_ADMIN'], null));
        $this->httpClient->request('GET', '/api/depense/summary');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertEquals(350.0, $body['thisMonthPlanned'], 'admin must see the combined total across every bureau');
    }

    public function testSummaryStaffSeesOnlyOwnBureauTotal(): void
    {
        $a = $this->makeBureau('Summary Sentinel A2');
        $b = $this->makeBureau('Summary Sentinel B2');
        $this->seedThisMonthStandaloneDepense($a, '100.00');
        $this->seedThisMonthStandaloneDepense($b, '250.00');

        $this->httpClient->loginUser($this->makeUser('summary-staffA@example.test', ['ROLE_STAFF'], $a));
        $this->httpClient->request('GET', '/api/depense/summary');

        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertEquals(100.0, $body['thisMonthPlanned'], "bureau-A staff must see only bureau A's total");
    }

    public function testSummaryStaffWithNoBureauSeesZero(): void
    {
        $a = $this->makeBureau('Summary Sentinel A3');
        $b = $this->makeBureau('Summary Sentinel B3');
        $this->seedThisMonthStandaloneDepense($a, '100.00');
        $this->seedThisMonthStandaloneDepense($b, '250.00');

        $this->httpClient->loginUser($this->makeUser('summary-nobureau@example.test', ['ROLE_STAFF'], null));
        $this->httpClient->request('GET', '/api/depense/summary');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        self::assertEquals(0.0, $body['thisMonthPlanned'], 'a bureau-less non-admin must see zero, not the combined total');
    }
}
