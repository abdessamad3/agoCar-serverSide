<?php

namespace App\Tests\Api;

use App\Entity\Adblue;
use App\Entity\Assurance;
use App\Entity\Depense;
use App\Entity\PaiementDepense;
use App\Entity\Reparation;
use App\Entity\SuiviTechnique;
use App\Entity\Vidange;
use App\Entity\Vignette;
use App\Enum\StatusEnum;

/**
 * Phase 0.5: closes the confirmed bypass where a Depense (or the subtype
 * record that owns it) with real, non-deleted PaiementDepense history could
 * still be hard-deleted -- either through the generic /api/depense endpoint
 * (which had no guard at all) or through five of the six subtype endpoints
 * (Vignette/Reparation/Vidange/AdBlue/SuiviTechnique), none of which had any
 * payment guard; only Assurance did, and even that one was bypassable via the
 * generic endpoint since DepenseController::delete() didn't check anything.
 *
 * One HTTP request per test method (same harness constraint documented in
 * LocationControllerTest.php and used throughout AdblueBureauAccessTest.php)
 * -- payment history is seeded directly via the EntityManager so each test
 * spends its one request on the thing actually being verified.
 */
final class ExpensePaymentDeletionGuardTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // adblue/vignette/vidange/suivi_technique aren't in the base truncate
        // list (see AdblueBureauAccessTest's identical note for adblue) -- this
        // test seeds all four directly, so without this a stray row can
        // reference a depense/voiture id that TRUNCATE resets and a later
        // test (in any class, not just this one) reuses for something else.
        $conn = $this->em->getConnection();
        foreach (['adblue', 'vignette', 'vidange', 'suivi_technique'] as $table) {
            $conn->executeStatement("DELETE FROM `$table`");
        }
    }

    private function seedExpense(string $typeDepense, bool $withPayment, ?\App\Entity\Bureau $bureau = null): array
    {
        $bureau  = $bureau ?? $this->makeBureau('Guard ' . $typeDepense . '-' . uniqid());
        $voiture = $this->makeVoiture($bureau, 'GRD-' . strtoupper(substr($typeDepense, 0, 3)) . uniqid());

        $depense = new Depense();
        $depense->setBureau($bureau)->setVoiture($voiture)->setDateDebut(new \DateTimeImmutable('today'))
            ->setDateFin(new \DateTimeImmutable('+1 year'))
            ->setTypeDepense($typeDepense)->setMontant('1000.00')
            ->setMontantPaye($withPayment ? '1000.00' : '0')
            ->setStatut($withPayment ? StatusEnum::PAYEE : StatusEnum::IMPAYE)
            ->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->flush();

        if ($withPayment) {
            $paiement = new PaiementDepense();
            $paiement->setDepense($depense)->setMontant('1000.00')->setDatePaiement(new \DateTimeImmutable('today'))
                ->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($paiement);
            $this->em->flush();
        }

        return [$depense, $bureau, $voiture];
    }

    private function admin(string $email): \App\Entity\Utilisateur
    {
        return $this->makeUser($email, ['ROLE_ADMIN'], null);
    }

    // ── Generic Depense endpoint ─────────────────────────────────────────────

    public function testGenericDepenseDeleteBlockedWithPayments(): void
    {
        [$depense] = $this->seedExpense('loyer', withPayment: true);
        $this->httpClient->loginUser($this->admin('g1@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        $resp = $this->httpClient->getResponse();
        self::assertSame(422, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        self::assertSame('has_payments', $body['error']);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Depense::class)->find($depense->getId());
        self::assertNull($reloaded->getDeletedAt(), 'blocked delete must not soft-delete');
        self::assertSame('1000.00', $reloaded->getMontantPaye(), 'montantPaye must be untouched by a blocked delete');
    }

    public function testGenericDepenseDeleteAllowedWithoutPayments(): void
    {
        [$depense] = $this->seedExpense('loyer', withPayment: false);
        $this->httpClient->loginUser($this->admin('g2@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());

        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $reloaded = $this->em->getRepository(Depense::class)->find($depense->getId());
        self::assertNotNull($reloaded->getDeletedAt(), 'unpaid expense must still be deletable');
    }

    /** The originally-reported bypass: Assurance's own guard refuses, but the
     *  same underlying expense used to be deletable anyway via /api/depense. */
    public function testGenericDepenseDeleteNoLongerBypassesAssuranceGuard(): void
    {
        [$depense] = $this->seedExpense('assurance', withPayment: true);
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Test Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('g3@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode());
        $this->em->clear();
        $reloaded = $this->em->getRepository(Depense::class)->find($depense->getId());
        self::assertNull($reloaded->getDeletedAt(), 'the generic endpoint must no longer bypass the subtype guard');
    }

    // ── Vignette ─────────────────────────────────────────────────────────────

    public function testVignetteDeleteBlockedWithPayments(): void
    {
        [$depense] = $this->seedExpense('vignette', withPayment: true);
        $vignette = new Vignette();
        $vignette->setDepense($depense)->setAnnee((int) date('Y'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vignette);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('v1@example.test'));
        $this->httpClient->request('DELETE', '/api/vignette/' . $vignette->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode());
        $this->em->clear();
        $reloaded = $this->em->getRepository(Vignette::class)->find($vignette->getId());
        self::assertNull($reloaded->getDeletedAt());
    }

    public function testVignetteDeleteAllowedWithoutPayments(): void
    {
        [$depense] = $this->seedExpense('vignette', withPayment: false);
        $vignette = new Vignette();
        $vignette->setDepense($depense)->setAnnee((int) date('Y'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vignette);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('v2@example.test'));
        $this->httpClient->request('DELETE', '/api/vignette/' . $vignette->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Assurance (existing guard, must be preserved exactly) ───────────────

    public function testAssuranceDeleteBlockedWithPaymentsKeepsExistingMessage(): void
    {
        [$depense] = $this->seedExpense('assurance', withPayment: true);
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Test Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('a1@example.test'));
        $this->httpClient->request('DELETE', '/api/assurance/' . $assurance->getId());

        $resp = $this->httpClient->getResponse();
        self::assertSame(422, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        self::assertSame('has_payments', $body['error']);
        self::assertStringContainsString('Annuler', $body['message'], 'the pre-existing Assurance guidance message must be unchanged');
    }

    public function testAssuranceDeleteAllowedWithoutPayments(): void
    {
        [$depense] = $this->seedExpense('assurance', withPayment: false);
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Test Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('a2@example.test'));
        $this->httpClient->request('DELETE', '/api/assurance/' . $assurance->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Reparation ───────────────────────────────────────────────────────────

    public function testReparationDeleteBlockedWithPayments(): void
    {
        [$depense] = $this->seedExpense('reparation', withPayment: true);
        $reparation = new Reparation();
        $reparation->setDepense($depense)->setDescriptionTechnique('Test repair')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('r1@example.test'));
        $this->httpClient->request('DELETE', '/api/reparation/' . $reparation->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testReparationDeleteAllowedWithoutPayments(): void
    {
        [$depense] = $this->seedExpense('reparation', withPayment: false);
        $reparation = new Reparation();
        $reparation->setDepense($depense)->setDescriptionTechnique('Test repair')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('r2@example.test'));
        $this->httpClient->request('DELETE', '/api/reparation/' . $reparation->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Vidange ──────────────────────────────────────────────────────────────

    public function testVidangeDeleteBlockedWithPayments(): void
    {
        [$depense] = $this->seedExpense('vidange', withPayment: true);
        $vidange = new Vidange();
        $vidange->setDepense($depense)->setKilometrageSuivant(20000)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vidange);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('vi1@example.test'));
        $this->httpClient->request('DELETE', '/api/vidange/' . $vidange->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testVidangeDeleteAllowedWithoutPayments(): void
    {
        [$depense] = $this->seedExpense('vidange', withPayment: false);
        $vidange = new Vidange();
        $vidange->setDepense($depense)->setKilometrageSuivant(20000)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vidange);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('vi2@example.test'));
        $this->httpClient->request('DELETE', '/api/vidange/' . $vidange->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── AdBlue ───────────────────────────────────────────────────────────────

    public function testAdblueDeleteBlockedWithPayments(): void
    {
        [$depense] = $this->seedExpense('adblue', withPayment: true);
        $adblue = new Adblue();
        $adblue->setDepense($depense)->setQuantiteLitre(5)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($adblue);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('ab1@example.test'));
        $this->httpClient->request('DELETE', '/api/adblue/' . $adblue->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testAdblueDeleteAllowedWithoutPayments(): void
    {
        [$depense] = $this->seedExpense('adblue', withPayment: false);
        $adblue = new Adblue();
        $adblue->setDepense($depense)->setQuantiteLitre(5)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($adblue);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('ab2@example.test'));
        $this->httpClient->request('DELETE', '/api/adblue/' . $adblue->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Suivi Technique ──────────────────────────────────────────────────────

    public function testSuiviTechniqueDeleteBlockedWithPayments(): void
    {
        [$depense, , $voiture] = $this->seedExpense('suivi_technique', withPayment: true);
        $suivi = new SuiviTechnique();
        $suivi->setDepense($depense)->setVoiture($voiture)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($suivi);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('st1@example.test'));
        $this->httpClient->request('DELETE', '/api/suivi-technique/' . $suivi->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testSuiviTechniqueDeleteAllowedWithoutPayments(): void
    {
        [$depense, , $voiture] = $this->seedExpense('suivi_technique', withPayment: false);
        $suivi = new SuiviTechnique();
        $suivi->setDepense($depense)->setVoiture($voiture)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($suivi);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('st2@example.test'));
        $this->httpClient->request('DELETE', '/api/suivi-technique/' . $suivi->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Cross-cutting: bureau check still takes priority over payment check ─

    public function testCrossBureauDeleteStillReturnsNotFoundBeforePaymentCheck(): void
    {
        $bureauA = $this->makeBureau('Priority A');
        [$depense] = $this->seedExpense('reparation', withPayment: true, bureau: $this->makeBureau('Priority B'));
        $reparation = new Reparation();
        $reparation->setDepense($depense)->setDescriptionTechnique('cross-bureau test')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $this->em->flush();

        $this->httpClient->loginUser($this->makeUser('priority-manager@example.test', ['ROLE_MANAGER'], $bureauA));
        $this->httpClient->request('DELETE', '/api/reparation/' . $reparation->getId());

        // 404 (bureau mismatch), not 422 (payment guard) -- bureau isolation
        // must still be checked first, regardless of payment history.
        self::assertSame(404, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Legitimate historical-record paths must remain unaffected ───────────

    public function testAssuranceCancelStillWorksWithPaymentHistory(): void
    {
        [$depense] = $this->seedExpense('assurance', withPayment: true);
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Test Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('cancel1@example.test'));
        $this->httpClient->request('POST', '/api/assurance/' . $assurance->getId() . '/cancel');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());
    }

    public function testVignetteRenewStillWorksWithPaymentHistoryOnOldRecord(): void
    {
        [$depense, $bureau, $voiture] = $this->seedExpense('vignette', withPayment: true);
        $vignette = new Vignette();
        $vignette->setDepense($depense)->setAnnee((int) date('Y'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vignette);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('renew1@example.test'));
        $this->httpClient->request(
            'POST',
            '/api/vignette/' . $vignette->getId() . '/renew',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['dateLimite' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'), 'montant' => 700])
        );

        self::assertSame(201, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());

        // The old vignette+depense (which has real payment history) must still
        // end up soft-deleted by renew() -- that's its intended behavior and is
        // NOT subject to the new delete guard, which only applies to the
        // explicit delete() actions.
        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $oldVignette = $this->em->getRepository(Vignette::class)->find($vignette->getId());
        self::assertNotNull($oldVignette->getDeletedAt(), 'renew must still retire the old record even though it has payment history');
    }
}
