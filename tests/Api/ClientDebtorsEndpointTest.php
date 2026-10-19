<?php

namespace App\Tests\Api;

use App\Entity\Client;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\Voiture;
use App\Enum\StatusEnum;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.9 Stage B1 verification: GET /api/client/debtors (the server-side-paginated,
 * debt-ranked endpoint behind the Angular "customer debtors" page).
 *
 * The refund-related tests in this file assert the CORRECT, refund-aware balance
 * (Reservation::getBalance()'s formula, replicated by ReservationRepository::sumOwed()/
 * refundSubDql() since Phase 12.9 Stage B1-F). Before Stage B1-F, one of these tests
 * intentionally pinned a known-wrong inflated value; see the Stage B1 final-verification
 * report (Task 1) and the Stage B1-F report for the investigation and fix.
 */
final class ClientDebtorsEndpointTest extends ApiTestCase
{
    private function makeVoitureNoBureau(): Voiture
    {
        $bureau = $this->makeBureau('Bureau Sans Filtrage');
        return $this->makeVoiture($bureau, 'NB-' . uniqid());
    }

    /** total=$total, montantPaye=$paye, no refund. Mirrors ApiTestCase::makeReservation()
     *  but with a controllable amount instead of the fixed 500.00. $status is set before
     *  the initial persist, same as ApiTestCase::makeReservation() -- ReservationStatusWriteGuard
     *  only forbids direct writes to an ALREADY-persisted row, not the initial value. */
    private function makeDebtReservation(Voiture $voiture, Client $client, string $total, string $paye = '0.00', string $status = 'confirmed'): Reservation
    {
        $r = new Reservation();
        $r->setVoiture($voiture);
        $r->setClient($client);
        $r->setDateDebut(new \DateTimeImmutable('today'));
        $r->setDateFin(new \DateTimeImmutable('+2 days'));
        $r->setTotal($total);
        $r->setMontantPaye($paye);
        $r->setReservationStatus($status);
        $r->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($r);
        $this->em->flush();
        return $r;
    }

    private function makeRefundPaiement(Reservation $r, string $negativeMontant): Paiement
    {
        $p = new Paiement();
        $p->setReservation($r);
        $p->setMontant($negativeMontant);
        $p->setDatePaiement(new \DateTimeImmutable());
        $p->setStatut(StatusEnum::PAYEE);
        $p->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($p);
        $this->em->flush();
        return $p;
    }

    /** $count clients, each with one unpaid reservation of exactly $debtEach MAD.
     *  Bulk-persisted (2 flushes total) so a 100+ fixture test stays fast. */
    private function makeManyDebtors(Voiture $voiture, int $count, string $debtEach): array
    {
        $clients = [];
        for ($i = 0; $i < $count; $i++) {
            $c = new Client();
            $c->setNom('Debtor' . $i);
            $c->setTelephone('0600000000');
            $c->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($c);
            $clients[] = $c;
        }
        $this->em->flush();

        foreach ($clients as $c) {
            $r = new Reservation();
            $r->setVoiture($voiture);
            $r->setClient($c);
            $r->setDateDebut(new \DateTimeImmutable('today'));
            $r->setDateFin(new \DateTimeImmutable('+2 days'));
            $r->setTotal($debtEach);
            $r->setMontantPaye('0.00');
            $r->setReservationStatus('confirmed');
            $r->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($r);
        }
        $this->em->flush();

        return $clients;
    }

    private function body(): array
    {
        return json_decode($this->httpClient->getResponse()->getContent(), true);
    }

    /** Global (server-wide) query counter -- session-level counters don't work here
     *  because KernelBrowser::request() reboots the kernel (and its DBAL connection)
     *  before each request, so the HTTP call runs on a different connection than
     *  $this->em's. Safe for an isolated single-threaded test run against the
     *  dedicated test database; would be noisy under concurrent DB traffic. */
    private function globalQuestionsCounter(): int
    {
        $row = $this->em->getConnection()->fetchAssociative("SHOW GLOBAL STATUS LIKE 'Questions'");
        return (int) $row['Value'];
    }

    // ── Authorization ────────────────────────────────────────────────────────────

    public function testAnonymousCannotAccessDebtors(): void
    {
        $this->httpClient->request('GET', '/api/client/debtors');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testBareRoleUserCannotAccessDebtors(): void
    {
        // What AuthController::register() actually produces -- must stay denied
        // by the firewall's access_control catch-all, same as every other admin route.
        $bare = $this->makeUser('bare@test.local', ['ROLE_USER'], null);
        $this->httpClient->loginUser($bare);
        $this->httpClient->request('GET', '/api/client/debtors');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testClientPortalRoleCannotAccessDebtors(): void
    {
        $client = $this->makeUser('customer@test.local', ['ROLE_CLIENT'], null);
        $this->httpClient->loginUser($client);
        $this->httpClient->request('GET', '/api/client/debtors');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testStaffCanAccessDebtorsAndSeesCorrectAmount(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'ST-001');
        $client = $this->makeClientEntity('Benali');
        $this->makeDebtReservation($voiture, $client, '1000.00', '400.00'); // owes 600

        $staff = $this->makeUser('staff@test.local', ['ROLE_STAFF'], $bureau);
        $this->httpClient->loginUser($staff);
        $this->httpClient->request('GET', '/api/client/debtors');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = $this->body();
        $this->assertCount(1, $body['data']);
        $this->assertSame(600.0, (float) $body['data'][0]['outstandingDebt']);
        $this->assertSame(600.0, (float) $body['meta']['grandTotal']);
    }

    public function testManagerCanAccessDebtors(): void
    {
        $bureau = $this->makeBureau('Bureau M');
        $voiture = $this->makeVoiture($bureau, 'MG-001');
        $client = $this->makeClientEntity('Alami');
        $this->makeDebtReservation($voiture, $client, '500.00', '0.00');

        $manager = $this->makeUser('manager@test.local', ['ROLE_MANAGER'], $bureau);
        $this->httpClient->loginUser($manager);
        $this->httpClient->request('GET', '/api/client/debtors');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Bureau visibility/isolation ──────────────────────────────────────────────

    public function testBureauIsolation(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $voitureA = $this->makeVoiture($bureauA, 'BA-001');
        $voitureB = $this->makeVoiture($bureauB, 'BB-001');
        $clientA = $this->makeClientEntity('ClientA');
        $clientB = $this->makeClientEntity('ClientB');
        $this->makeDebtReservation($voitureA, $clientA, '1000.00', '0.00');
        $this->makeDebtReservation($voitureB, $clientB, '2000.00', '0.00');

        $staffA = $this->makeUser('staffA@test.local', ['ROLE_STAFF'], $bureauA);
        $this->httpClient->loginUser($staffA);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertCount(1, $body['data'], 'Bureau A staff must not see Bureau B\'s debtor');
        $this->assertSame('ClientA', $body['data'][0]['nom']);
        $this->assertSame(1000.0, (float) $body['meta']['grandTotal'], 'Grand total must be scoped to the visible bureau only');
    }

    public function testAdminWithNoBureauSeesAllBureaus(): void
    {
        $bureauA = $this->makeBureau('Bureau A');
        $bureauB = $this->makeBureau('Bureau B');
        $voitureA = $this->makeVoiture($bureauA, 'AA-001');
        $voitureB = $this->makeVoiture($bureauB, 'AB-001');
        $this->makeDebtReservation($voitureA, $this->makeClientEntity('ClientA'), '1000.00', '0.00');
        $this->makeDebtReservation($voitureB, $this->makeClientEntity('ClientB'), '2000.00', '0.00');

        $admin = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertCount(2, $body['data']);
        $this->assertSame(3000.0, (float) $body['meta']['grandTotal']);
    }

    // ── Debt amount correctness ──────────────────────────────────────────────────

    public function testDebtSummedAcrossMultipleReservationsForSameClient(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('MultiRes');
        $this->makeDebtReservation($voiture, $client, '1000.00', '700.00'); // owes 300
        $this->makeDebtReservation($voiture, $client, '500.00', '100.00');  // owes 400

        $admin = $this->makeUser('admin2@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertCount(1, $body['data']);
        $this->assertSame(700.0, (float) $body['data'][0]['outstandingDebt']);
    }

    public function testCancelledReservationNeverCountsAsDebt(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('Cancelled');
        $this->makeDebtReservation($voiture, $client, '1000.00', '0.00', 'annulee');

        $admin = $this->makeUser('admin3@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertSame(0, $body['meta']['total']);
    }

    // ── Ordering ──────────────────────────────────────────────────────────────────

    public function testDescendingOrderAndTieBreaking(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $c1 = $this->makeClientEntity('Low');     // debt 100, created first (lowest id)
        $c2 = $this->makeClientEntity('TieOld');  // debt 300
        $c3 = $this->makeClientEntity('TieNew');  // debt 300, created last (highest id)
        $this->makeDebtReservation($voiture, $c1, '100.00', '0.00');
        $this->makeDebtReservation($voiture, $c2, '300.00', '0.00');
        $this->makeDebtReservation($voiture, $c3, '300.00', '0.00');

        $admin = $this->makeUser('admin4@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $ids = array_map(fn($d) => $d['id'], $body['data']);
        // Highest debt first; among the 300/300 tie, the newer client (higher id) wins.
        $this->assertSame([$c3->getId(), $c2->getId(), $c1->getId()], $ids);
    }

    // ── Search ────────────────────────────────────────────────────────────────────

    public function testSearchFiltersByName(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $alpha = $this->makeClientEntity('Alpha');
        $beta  = $this->makeClientEntity('Beta');
        $this->makeDebtReservation($voiture, $alpha, '500.00', '0.00');
        $this->makeDebtReservation($voiture, $beta, '500.00', '0.00');

        $admin = $this->makeUser('admin5@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?search=Beta');

        $body = $this->body();
        $this->assertCount(1, $body['data']);
        $this->assertSame('Beta', $body['data'][0]['nom']);
    }

    // ── Empty results ─────────────────────────────────────────────────────────────

    public function testEmptyResultWhenNoDebtors(): void
    {
        $admin = $this->makeUser('admin6@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['total']);
        $this->assertSame(0.0, (float) $body['meta']['grandTotal']);
        $this->assertSame(0, $body['meta']['highRiskCount']);
    }

    public function testFullyPaidReservationIsNotADebtor(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('PaidUp');
        $this->makeDebtReservation($voiture, $client, '500.00', '500.00');

        $admin = $this->makeUser('admin7@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertSame(0, $body['meta']['total']);
    }

    // ── Invalid / boundary page & limit parameters ───────────────────────────────

    public function testNegativePageClampsToPageOne(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $this->makeDebtReservation($voiture, $this->makeClientEntity('X'), '500.00', '0.00');

        $admin = $this->makeUser('admin8@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?page=-5');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = $this->body();
        $this->assertSame(1, $body['meta']['page']);
        $this->assertCount(1, $body['data']);
    }

    public function testNonNumericPageClampsToPageOne(): void
    {
        $admin = $this->makeUser('admin9@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?page=abc');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame(1, $this->body()['meta']['page']);
    }

    public function testOversizedPageReturnsEmptyDataNotError(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $this->makeDebtReservation($voiture, $this->makeClientEntity('Y'), '500.00', '0.00');

        $admin = $this->makeUser('admin10@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?page=9999');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = $this->body();
        $this->assertSame([], $body['data']);
        $this->assertFalse($body['meta']['hasNextPage']);
    }

    /** An arbitrary ?limit= must be ignored, same as every other paginated endpoint in
     *  this app (PaginationTrait::PER_PAGE is a fixed server-side constant, never read
     *  from the request) -- not a defect specific to this endpoint. */
    public function testArbitraryLimitParameterIsIgnored(): void
    {
        $admin = $this->makeUser('admin11@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?limit=999999');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame(100, $this->body()['meta']['limit']);
    }

    // ── Pagination boundaries across multiple pages ──────────────────────────────

    /** Stateless-firewall constraint: loginUser() only authenticates the immediately-next
     *  request, so page 1 and page 2 must be separate test methods (one HTTP request per
     *  test method), each rebuilding the same 105-debtor dataset in its own setUp(). */
    private function seed105DebtorsAt100Each(): Voiture
    {
        $voiture = $this->makeVoitureNoBureau();
        // 105 debtors at 100.00 each -> 2 pages (100 + 5), well under the 'high' (>=5000) tier.
        $this->makeManyDebtors($voiture, 105, '100.00');
        return $voiture;
    }

    public function testGrandTotalAndHighRiskCountOnPageOneOfTwo(): void
    {
        $this->seed105DebtorsAt100Each();

        $admin = $this->makeUser('admin12@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?page=1');
        $page1 = $this->body();

        $this->assertCount(100, $page1['data']);
        $this->assertSame(105, $page1['meta']['total']);
        $this->assertSame(2, $page1['meta']['totalPages']);
        $this->assertTrue($page1['meta']['hasNextPage']);
        $this->assertFalse($page1['meta']['hasPrevPage']);
        $this->assertSame(10500.0, (float) $page1['meta']['grandTotal']); // 105 * 100
        $this->assertSame(0, $page1['meta']['highRiskCount']);
    }

    public function testGrandTotalAndHighRiskCountOnPageTwoOfTwo(): void
    {
        $this->seed105DebtorsAt100Each();

        $admin = $this->makeUser('admin12b@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors?page=2');
        $page2 = $this->body();

        $this->assertCount(5, $page2['data']);
        $this->assertSame(105, $page2['meta']['total']);
        $this->assertFalse($page2['meta']['hasNextPage']);
        $this->assertTrue($page2['meta']['hasPrevPage']);
        // Grand total/high-risk count are GLOBAL aggregates -- identical on every page,
        // not recomputed from whichever 100 (or 5) rows happen to be on this page.
        $this->assertSame(10500.0, (float) $page2['meta']['grandTotal']);

        // None of page 2's ids are among the first 100 (by creation order, since all debts
        // are tied at 100.00 -- the tie-break is "newest id first", so page 2 holds the 5
        // OLDEST of the 105 clients created in seed105DebtorsAt100Each()).
        $page2Ids = array_column($page2['data'], 'id');
        $this->assertCount(5, array_unique($page2Ids));
    }

    // ── Performance (Task 4) ──────────────────────────────────────────────────────

    /** Phase 12.9 Stage B1 verification, Task 4: the endpoint must not issue a
     *  per-client query (N+1) -- query count should stay small and roughly constant
     *  whether there are 7 debtors or 500. This test alone proves "small"; combined
     *  with testQueryCountForSmallDebtorSetForComparison() (same bound), it proves
     *  "doesn't scale with N" without needing cross-test-method state sharing. */
    public function testQueryCountStaysBoundedForFiveHundredDebtors(): void
    {
        $this->makeManyDebtors($this->makeVoitureNoBureau(), 500, '100.00');

        $admin = $this->makeUser('perf500@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);

        $memBefore = memory_get_peak_usage(true);
        $before = $this->globalQuestionsCounter();
        $this->httpClient->request('GET', '/api/client/debtors?page=1');
        $after = $this->globalQuestionsCounter();
        $memAfter = memory_get_peak_usage(true);

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertCount(100, $this->body()['data']);

        $queryCount = $after - $before - 1; // -1 for globalQuestionsCounter()'s own trailing SHOW STATUS call
        fwrite(STDERR, "\n>>> [debtors perf] 500 debtors: ~$queryCount SQL statements, peak memory delta " . round(($memAfter - $memBefore) / 1024 / 1024, 2) . " MB\n");
        $this->assertLessThan(15, $queryCount, "Query count grew with debtor count (N+1 suspected): $queryCount queries for 500 debtors");
    }

    public function testQueryCountForSmallDebtorSetForComparison(): void
    {
        $this->makeManyDebtors($this->makeVoitureNoBureau(), 5, '100.00');

        $admin = $this->makeUser('perf5@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);

        $before = $this->globalQuestionsCounter();
        $this->httpClient->request('GET', '/api/client/debtors?page=1');
        $after = $this->globalQuestionsCounter();

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $queryCount = $after - $before - 1;
        fwrite(STDERR, ">>> [debtors perf] 5 debtors: ~$queryCount SQL statements\n");
        // Same bound as the 500-debtor test -- if these two numbers are close, the
        // endpoint's query count is independent of debtor count, as intended.
        $this->assertLessThan(15, $queryCount);
    }

    public function testHighRiskCountCrossesThreshold(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $this->makeDebtReservation($voiture, $this->makeClientEntity('HighRisk'), '6000.00', '0.00');
        $this->makeDebtReservation($voiture, $this->makeClientEntity('LowRisk'), '200.00', '0.00');

        $admin = $this->makeUser('admin13@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $this->assertSame(1, $this->body()['meta']['highRiskCount']);
    }

    // ── Refund edge cases (Task 1 investigation) ─────────────────────────────────

    /** Correct-formula check: a reservation that was fully paid and then refunded is
     *  genuinely settled (Reservation::getBalance() == 0) and must not appear as a
     *  debtor on its own. This part is CORRECT today -- applyHasDebtFilter() already
     *  adds the refund back before comparing, matching getBalance(). */
    public function testRefundOnAlreadySettledReservationAloneIsNotADebtor(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('RefundedSettled');
        $r = $this->makeDebtReservation($voiture, $client, '1000.00', '1000.00'); // fully paid
        $this->makeRefundPaiement($r, '-200.00');
        $r->setMontantPaye('800.00'); // mirrors PaiementController::recomputeReservationMontantPaye()
        $this->em->flush();

        $admin = $this->makeUser('admin14@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $this->assertSame(0, $this->body()['meta']['total'], 'A refund on an already-settled reservation must not create a phantom debtor');
    }

    /**
     * FIXED in Phase 12.9 Stage B1-F (previously a documented pre-existing defect -- see
     * the Stage B1 final-verification report, Task 1). A client who genuinely owes money
     * on one reservation AND has a separate, already-settled-then-refunded reservation
     * must see only their REAL debt (300.00, from the second reservation), not an
     * inflated figure (500.00 = 300 real + 200 phantom from the refund). Before the fix,
     * ReservationRepository::sumOwed() computed each reservation's balance as the naive
     * `montantPaye - total`, without adding back getMontantRembourse() the way
     * Reservation::getBalance() (the authoritative formula) does; it now mirrors
     * getBalance() exactly via refundSubDql(). This assertion was changed from pinning
     * the old wrong value (500.00) to the correct one (300.00), per the Stage B1-F
     * instruction to update rather than delete/weaken this test.
     */
    public function testRefundOnSettledReservationDoesNotInflateDisplayedDebtWhenClientHasOtherRealDebt(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('MixedRefundAndRealDebt');

        $settled = $this->makeDebtReservation($voiture, $client, '1000.00', '1000.00');
        $this->makeRefundPaiement($settled, '-200.00');
        $settled->setMontantPaye('800.00');
        $this->em->flush();

        $this->makeDebtReservation($voiture, $client, '500.00', '200.00'); // genuinely owes 300

        $admin = $this->makeUser('admin15@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $body = $this->body();
        $this->assertCount(1, $body['data'], 'Client is correctly included (real debt exists on the second reservation)');
        $this->assertSame(
            300.0,
            (float) $body['data'][0]['outstandingDebt'],
            'Corrected: the refund on the already-settled reservation must not inflate the real 300.00 debt'
        );
    }

    /**
     * Refund on a still-open, PARTIALLY paid reservation (not fully settled). This locks
     * in an intentional, mathematically-guaranteed property of the authoritative formula
     * (Reservation::getBalance() = montantPaye + getMontantRembourse() - total): because
     * a refund always decreases montantPaye by exactly the amount getMontantRembourse()
     * then adds back, getBalance() is ALGEBRAICALLY INVARIANT under any refund -- a
     * refund never changes the displayed balance, whether the reservation was settled,
     * overpaid, or (as here) still only partially paid. This is a property of
     * getBalance() itself (out of scope to change per Stage B1-F's "stop and report if
     * the authoritative formula appears inconsistent" instruction -- it does not; it is
     * self-consistent by construction), not something introduced by this stage's fix.
     * total=1000, paid 600 (owes 400) -> refund 100 (allowed: 100 <= 600 already paid) ->
     * net paid becomes 500, refunded=100 -> debt stays 400, both before and after.
     */
    public function testOutstandingDebtBeforeRefundOnPartiallyPaidReservation(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('PartialThenRefunded');
        $this->makeDebtReservation($voiture, $client, '1000.00', '600.00'); // owes 400

        $admin = $this->makeUser('admin18a@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $this->assertSame(400.0, (float) $this->body()['data'][0]['outstandingDebt']);
    }

    /** Same scenario as above, but with the refund already applied before the (one and
     *  only) request -- stateless-firewall tests get one HTTP request per test method,
     *  so "before" and "after" must be separate methods, not two requests in one. */
    public function testOutstandingDebtAfterRefundOnPartiallyPaidReservationIsUnchanged(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('PartialThenRefunded2');
        $r = $this->makeDebtReservation($voiture, $client, '1000.00', '600.00'); // owes 400
        $this->makeRefundPaiement($r, '-100.00');
        $r->setMontantPaye('500.00'); // mirrors PaiementController::recomputeReservationMontantPaye()
        $this->em->flush();

        $admin = $this->makeUser('admin18b@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/debtors');

        $this->assertSame(
            400.0,
            (float) $this->body()['data'][0]['outstandingDebt'],
            'A refund must never change the displayed balance (see docblock on the "before" test)'
        );
    }

    /** GET /api/client/{id} (show()) must display the same corrected amount as
     *  GET /api/client/debtors for the same client/scenario. */
    public function testClientShowEndpointReflectsCorrectedDebt(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('ShowEndpointCheck');
        $settled = $this->makeDebtReservation($voiture, $client, '1000.00', '1000.00');
        $this->makeRefundPaiement($settled, '-200.00');
        $settled->setMontantPaye('800.00');
        $this->em->flush();
        $this->makeDebtReservation($voiture, $client, '500.00', '200.00'); // genuinely owes 300

        $admin = $this->makeUser('admin19@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/' . $client->getId());

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame(300.0, (float) $this->body()['outstandingDebt']);
    }

    /** GET /api/client/{id}/financial-summary must apply the same refund-aware
     *  correction to both outstandingDebt and overpayments, and must keep its exact
     *  existing response shape (totalRentals/totalRevenue/totalPaid/outstandingDebt/
     *  overpayments/lastPaymentDate) -- this field is currently unrendered by any
     *  Angular template (confirmed by repo-wide search), but the API contract itself
     *  must not change regardless. */
    public function testClientFinancialSummaryReflectsCorrectedDebtAndKeepsShape(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('FinancialSummaryCheck');
        // Closed (terminee) + refunded -- only CLOSED_STATUSES count toward this summary's
        // revenue/debt/overpaid figures.
        $settled = $this->makeDebtReservation($voiture, $client, '1000.00', '1000.00', 'terminee');
        $this->makeRefundPaiement($settled, '-200.00');
        $settled->setMontantPaye('800.00');
        $this->em->flush();
        $this->makeDebtReservation($voiture, $client, '500.00', '200.00', 'terminee'); // genuinely owes 300

        $admin = $this->makeUser('admin20@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client/' . $client->getId() . '/financial-summary');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = $this->body();
        $this->assertSame(['totalRentals', 'totalRevenue', 'totalPaid', 'outstandingDebt', 'overpayments', 'lastPaymentDate'], array_keys($body));
        $this->assertSame(2, $body['totalRentals']);
        $this->assertSame(1500.0, (float) $body['totalRevenue']); // 1000 + 500
        $this->assertSame(1000.0, (float) $body['totalPaid']);    // 800 (net) + 200
        $this->assertSame(300.0, (float) $body['outstandingDebt']);
        $this->assertSame(0.0, (float) $body['overpayments']);
    }

    // ── GET /api/reservation?clientId= compatibility (Task 2) ───────────────────

    /** The only existing consumer (client-detail.component.ts's reservation-history
     *  tab) must keep working: ReservationController::list() now returns every
     *  matching row when clientId is given, the same way it already did for
     *  voitureId, instead of silently capping at PaginationTrait::PER_PAGE (100). */
    public function testReservationListByClientIdReturnsAllMatchingRowsUnpaginated(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $client = $this->makeClientEntity('ManyReservations');
        $other = $this->makeClientEntity('SomeoneElse');
        for ($i = 0; $i < 3; $i++) {
            $this->makeDebtReservation($voiture, $client, '500.00', '0.00');
        }
        $this->makeDebtReservation($voiture, $other, '500.00', '0.00'); // must not leak into the filtered result

        $admin = $this->makeUser('admin16@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/reservation?clientId=' . $client->getId());

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = $this->body();
        $this->assertCount(3, $body['data']);
        $this->assertSame(3, $body['meta']['total']);
        foreach ($body['data'] as $row) {
            $this->assertSame($client->getId(), $row['client']['id']);
        }
    }

    /** GET /api/client (list()) itself must be completely unaffected by the
     *  applyBureauVisibility()/applyHasDebtFilter() extraction -- its own hasDebt
     *  filter (already used in production by client-list.component.ts's "has debt"
     *  checkbox) must keep returning the same shape and the same matching set. */
    public function testPlainClientListHasDebtFilterStillWorks(): void
    {
        $voiture = $this->makeVoitureNoBureau();
        $this->makeDebtReservation($voiture, $this->makeClientEntity('OwesMoney'), '500.00', '0.00');
        $this->makeDebtReservation($voiture, $this->makeClientEntity('PaidUp2'), '500.00', '500.00');

        $admin = $this->makeUser('admin17@test.local', ['ROLE_ADMIN'], null);
        $this->httpClient->loginUser($admin);
        $this->httpClient->request('GET', '/api/client?hasDebt=1');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = $this->body();
        $this->assertCount(1, $body['data']);
        $this->assertSame('OwesMoney', $body['data'][0]['nom']);
        $this->assertArrayHasKey('outstandingDebt', $body['data'][0]);
        // list()'s own default order (c.id DESC) is untouched -- it does not rank by debt.
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayNotHasKey('grandTotal', $body['meta'], 'list() must not gain the new endpoint\'s extra meta fields');
    }
}
