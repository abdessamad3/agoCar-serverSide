<?php

namespace App\Tests\Api;

use App\Entity\Adblue;
use App\Entity\Assurance;
use App\Entity\Bureau;
use App\Entity\Depense;
use App\Entity\PaiementDepense;
use App\Entity\Reparation;
use App\Entity\SuiviTechnique;
use App\Entity\Vidange;
use App\Entity\Vignette;
use App\Enum\StatusEnum;

/**
 * Phase 0.6 (Option A): DepenseController::delete() used to be able to
 * soft-delete a Depense that was still referenced by an ACTIVE Vignette,
 * Assurance, Reparation, Vidange, AdBlue, or SuiviTechnique row -- none of
 * those subtypes' own tables got touched, leaving an orphan: an "active"
 * operational record silently pointing at a soft-deleted expense, invisible
 * in every list that joins through Depense (confirmed in the Phase 0.5
 * pre-deployment verification). This suite proves the new guard closes that
 * for all six types, without weakening the existing payment-history guard,
 * without touching bureau scoping, and without changing any subtype's own
 * delete/cancel/renew behavior.
 *
 * One HTTP request per test method (same harness constraint as
 * AdblueBureauAccessTest.php / ExpensePaymentDeletionGuardTest.php).
 */
final class DepenseGenericDeleteLinkedGuardTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // adblue/vignette/vidange/suivi_technique aren't in the base truncate
        // list (same note as ExpensePaymentDeletionGuardTest) -- this suite
        // seeds all four directly.
        $conn = $this->em->getConnection();
        foreach (['adblue', 'vignette', 'vidange', 'suivi_technique'] as $table) {
            $conn->executeStatement("DELETE FROM `$table`");
        }
    }

    private function seedExpense(string $typeDepense, ?Bureau $bureau = null): array
    {
        $bureau  = $bureau ?? $this->makeBureau('LinkGuard ' . $typeDepense . '-' . uniqid());
        $voiture = $this->makeVoiture($bureau, 'LG-' . strtoupper(substr($typeDepense, 0, 3)) . uniqid());

        $depense = new Depense();
        $depense->setBureau($bureau)->setVoiture($voiture)->setDateDebut(new \DateTimeImmutable('today'))
            ->setDateFin(new \DateTimeImmutable('+1 year'))
            ->setTypeDepense($typeDepense)->setMontant('500.00')->setMontantPaye('0')
            ->setStatut(StatusEnum::IMPAYE)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->flush();

        return [$depense, $bureau, $voiture];
    }

    private function admin(string $email): \App\Entity\Utilisateur
    {
        return $this->makeUser($email, ['ROLE_ADMIN'], null);
    }

    private function assertBlockedAndUntouched(Depense $depense): void
    {
        $resp = $this->httpClient->getResponse();
        self::assertSame(422, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        self::assertSame('linked_to_active_record', $body['error']);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Depense::class)->find($depense->getId());
        self::assertNull($reloaded->getDeletedAt(), 'blocked delete must not soft-delete the depense');
        self::assertSame('0.00', (string) $reloaded->getMontantPaye(), 'montantPaye must be untouched');
    }

    // ── All six types, generic delete blocked while linked and active ──────

    public function testGenericDeleteBlockedWhenLinkedToActiveVignette(): void
    {
        [$depense] = $this->seedExpense('vignette');
        $vignette = new Vignette();
        $vignette->setDepense($depense)->setAnnee((int) date('Y'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vignette);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-vig@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());
        $this->assertBlockedAndUntouched($depense);
    }

    public function testGenericDeleteBlockedWhenLinkedToActiveAssurance(): void
    {
        [$depense] = $this->seedExpense('assurance');
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Test Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-ass@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());
        $this->assertBlockedAndUntouched($depense);
    }

    public function testGenericDeleteBlockedWhenLinkedToActiveReparation(): void
    {
        [$depense] = $this->seedExpense('reparation');
        $reparation = new Reparation();
        $reparation->setDepense($depense)->setDescriptionTechnique('test')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-rep@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());
        $this->assertBlockedAndUntouched($depense);
    }

    public function testGenericDeleteBlockedWhenLinkedToActiveVidange(): void
    {
        [$depense] = $this->seedExpense('vidange');
        $vidange = new Vidange();
        $vidange->setDepense($depense)->setKilometrageSuivant(20000)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vidange);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-vid@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());
        $this->assertBlockedAndUntouched($depense);
    }

    public function testGenericDeleteBlockedWhenLinkedToActiveAdblue(): void
    {
        [$depense] = $this->seedExpense('adblue');
        $adblue = new Adblue();
        $adblue->setDepense($depense)->setQuantiteLitre(5)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($adblue);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-ab@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());
        $this->assertBlockedAndUntouched($depense);
    }

    public function testGenericDeleteBlockedWhenLinkedToActiveSuiviTechnique(): void
    {
        [$depense, , $voiture] = $this->seedExpense('suivi_technique');
        $suivi = new SuiviTechnique();
        $suivi->setDepense($depense)->setVoiture($voiture)->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($suivi);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-st@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());
        $this->assertBlockedAndUntouched($depense);
    }

    // ── Standalone / unpaid: must still be deletable ────────────────────────

    public function testGenericDeleteAllowedForStandaloneUnpaidExpense(): void
    {
        [$depense] = $this->seedExpense('loyer');
        $this->httpClient->loginUser($this->admin('lg-standalone@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $reloaded = $this->em->getRepository(Depense::class)->find($depense->getId());
        self::assertNotNull($reloaded->getDeletedAt());
    }

    // ── Payment-history guard preserved (and checked even when unlinked) ───

    public function testGenericDeleteStillBlockedByPaymentHistoryGuardAlone(): void
    {
        [$depense] = $this->seedExpense('loyer'); // standalone, no subtype link
        $depense->setMontantPaye('500.00')->setStatut(StatusEnum::PAYEE);
        $this->em->flush();
        $paiement = new PaiementDepense();
        $paiement->setDepense($depense)->setMontant('500.00')->setDatePaiement(new \DateTimeImmutable('today'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($paiement);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-paid@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        $resp = $this->httpClient->getResponse();
        self::assertSame(422, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        self::assertSame('has_payments', $body['error'], 'payment guard must still fire first, unaffected by the new link guard');
    }

    public function testGenericDeleteBlockedByPaymentHistoryEvenWhenAlsoLinked(): void
    {
        [$depense] = $this->seedExpense('reparation');
        $reparation = new Reparation();
        $reparation->setDepense($depense)->setDescriptionTechnique('paid + linked')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $depense->setMontantPaye('500.00')->setStatut(StatusEnum::PAYEE);
        $this->em->flush();
        $paiement = new PaiementDepense();
        $paiement->setDepense($depense)->setMontant('500.00')->setDatePaiement(new \DateTimeImmutable('today'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($paiement);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-paid-linked@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        $resp = $this->httpClient->getResponse();
        self::assertSame(422, $resp->getStatusCode());
        $body = json_decode($resp->getContent(), true);
        self::assertSame('has_payments', $body['error'], 'the payment guard, checked first, must still take priority');
    }

    // ── Cross-bureau authorization unaffected ───────────────────────────────

    public function testCrossBureauDeleteStillReturnsNotFoundBeforeLinkCheck(): void
    {
        $bureauA = $this->makeBureau('LinkGuard Priority A');
        [$depense] = $this->seedExpense('reparation', bureau: $this->makeBureau('LinkGuard Priority B'));
        $reparation = new Reparation();
        $reparation->setDepense($depense)->setDescriptionTechnique('cross-bureau')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $this->em->flush();

        $this->httpClient->loginUser($this->makeUser('lg-priority-manager@example.test', ['ROLE_MANAGER'], $bureauA));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(404, $this->httpClient->getResponse()->getStatusCode(), 'bureau isolation must still be checked first');
    }

    public function testSameBureauManagerCanDeleteStandaloneExpense(): void
    {
        $bureau = $this->makeBureau('LinkGuard Same Bureau');
        [$depense] = $this->seedExpense('loyer', bureau: $bureau);

        $this->httpClient->loginUser($this->makeUser('lg-same-manager@example.test', ['ROLE_MANAGER'], $bureau));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Soft-deleted / archived subtype edge cases ──────────────────────────

    public function testGenericDeleteAllowedWhenTheOnlyLinkedSubtypeIsAlreadySoftDeleted(): void
    {
        [$depense] = $this->seedExpense('vignette');
        $vignette = new Vignette();
        $vignette->setDepense($depense)->setAnnee((int) date('Y'))->setCreeAu(new \DateTimeImmutable());
        $vignette->setDeletedAt(new \DateTimeImmutable('-1 day')); // already retired
        $this->em->persist($vignette);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-already-deleted@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());
    }

    public function testGenericDeleteStillBlockedWhenLinkedAssuranceIsArchivedButNotDeleted(): void
    {
        // Archived (superseded by a renewal) is not the same as deleted -- its
        // Depense is still real historical data and must stay protected.
        [$depense] = $this->seedExpense('assurance');
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Archived Co')->setCreeAu(new \DateTimeImmutable());
        $assurance->setArchivedAt(new \DateTimeImmutable('-1 day'));
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-archived@example.test'));
        $this->httpClient->request('DELETE', '/api/depense/' . $depense->getId());

        self::assertSame(422, $this->httpClient->getResponse()->getStatusCode(), 'an archived-but-not-deleted Assurance must still block deletion');
    }

    // ── Existing cancellation / renewal behavior unaffected ─────────────────

    public function testAssuranceCancelStillWorksAndItsDepenseStaysProtectedAfterward(): void
    {
        [$depense] = $this->seedExpense('assurance');
        $assurance = new Assurance();
        $assurance->setDepense($depense)->setCompagnie('Cancel Co')->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-cancel@example.test'));
        $this->httpClient->request('POST', '/api/assurance/' . $assurance->getId() . '/cancel');

        self::assertSame(200, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());

        // cancel() doesn't set deletedAt -- the Assurance is still active in
        // the sense this guard cares about, so its Depense must still be
        // unreachable via the generic endpoint afterward.
        $this->em->clear();
        $stillLinked = $this->em->getRepository(Assurance::class)->findOneBy(['depense' => $depense]);
        self::assertNotNull($stillLinked, 'cancel() must not soft-delete the Assurance row');
    }

    public function testVignetteRenewStillWorksAndOldDepenseBecomesDeletableAfterward(): void
    {
        [$depense] = $this->seedExpense('vignette');
        $vignette = new Vignette();
        $vignette->setDepense($depense)->setAnnee((int) date('Y'))->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vignette);
        $this->em->flush();

        $this->httpClient->loginUser($this->admin('lg-renew@example.test'));
        $this->httpClient->request(
            'POST',
            '/api/vignette/' . $vignette->getId() . '/renew',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['dateLimite' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'), 'montant' => 700])
        );

        self::assertSame(201, $this->httpClient->getResponse()->getStatusCode(), $this->httpClient->getResponse()->getContent());

        // renew() soft-deletes the OLD vignette (and its depense) as part of
        // its own, unmodified behavior -- not through this new guard at all.
        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $oldVignette = $this->em->getRepository(Vignette::class)->find($vignette->getId());
        self::assertNotNull($oldVignette->getDeletedAt(), 'renew must still retire the old record exactly as before');
    }
}
