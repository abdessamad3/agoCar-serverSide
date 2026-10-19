<?php

namespace App\Tests\Api;

use App\Entity\AchatInstallment;
use App\Entity\AchatVoiture;
use App\Entity\Credit;
use App\Entity\FinancialInstitution;
use App\Entity\Vente;
use App\Entity\VehicleCredit;
use App\Entity\VehicleCreditDocument;
use App\Entity\VehicleCreditPayment;
use App\Entity\VehicleCreditReminder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.5 Stage 4: regression + new-restriction coverage for the 10
 * controllers audited in Phase 12.4. Every assertion here reflects a finding
 * independently re-verified against current source (not just Phase 12.4's
 * report) -- several of Phase 12.4's recommendations were REVERSED after
 * finding real ROLE_STAFF consumers via shared services/embedded panels on
 * the vehicle-detail page. See the Phase 12.5 final report for the full
 * narrative; this file only encodes the resulting, corrected behavior.
 */
final class RoleAuthorizationMatrixPhase125Test extends ApiTestCase
{
    // ── Anonymous / ROLE_CLIENT baseline (established semantics: this app's
    //    exception listener returns 403, not 401, for a role-gated path) ──

    public function testAnonymousCannotCreateACreditRecord(): void
    {
        $this->httpClient->request('POST', '/api/credit', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testRoleClientCannotCreateACreditRecord(): void
    {
        $client = $this->makeUser('client@test.local', ['ROLE_CLIENT'], null);
        $this->httpClient->loginUser($client);
        $this->httpClient->request('POST', '/api/credit', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── 1. CreditController (legacy) -- restricted to ADMIN/MANAGER this phase ──

    private function makeCredit(?\App\Entity\Bureau $bureau = null): Credit
    {
        $voiture = $this->makeVoiture($bureau ?? $this->makeBureau('Credit Bureau'), 'CR-' . uniqid());
        $credit  = new Credit();
        $credit->setMontantTotal('100000.00');
        $credit->setMensualite('2000.00');
        $credit->setDateDebut(new \DateTimeImmutable('today'));
        $credit->setDateFin(new \DateTimeImmutable('+4 years'));
        $credit->setDureeMois(48);
        $credit->setStatut('en_cours');
        $credit->setVoiture($voiture);
        $credit->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($credit);
        $this->em->flush();
        return $credit;
    }

    public function testStaffCannotCreateLegacyCreditRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/credit', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'montantTotal' => '100000', 'mensualite' => '2000', 'dateDebut' => '2026-01-01', 'dateFin' => '2030-01-01', 'dureeMois' => 48,
        ]));

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM credit'), 'Denied create must not write a row.');
    }

    public function testManagerCanCreateLegacyCreditRecord(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $manager = $this->makeUser('manager@test.local', ['ROLE_MANAGER'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'CR-MGR-0001');

        $this->httpClient->loginUser($manager);
        $this->httpClient->request('POST', '/api/credit', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'montantTotal' => '100000', 'mensualite' => '2000', 'dateDebut' => '2026-01-01', 'dateFin' => '2030-01-01', 'dureeMois' => 48, 'voitureId' => $voiture->getId(),
        ]));

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testPureAdminAccountCanCreateLegacyCreditRecord(): void
    {
        // Specifically an account with ONLY ['ROLE_ADMIN'] stored -- proves
        // the OR-check (not a bare ROLE_MANAGER attribute) correctly admits
        // admins even though there is no role hierarchy.
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);
        $voiture = $this->makeVoiture($this->makeBureau('Bureau A'), 'CR-ADM-0001');

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('POST', '/api/credit', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'montantTotal' => '100000', 'mensualite' => '2000', 'dateDebut' => '2026-01-01', 'dateFin' => '2030-01-01', 'dureeMois' => 48, 'voitureId' => $voiture->getId(),
        ]));

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testStaffCannotUpdateLegacyCreditRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff2@test.local', ['ROLE_STAFF'], $bureau);
        $credit = $this->makeCredit($bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('PUT', '/api/credit/' . $credit->getId(), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['statut' => 'termine']));
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $statut = $this->em->getConnection()->fetchOne('SELECT statut FROM credit WHERE id = ?', [$credit->getId()]);
        $this->assertSame('en_cours', $statut, 'Denied update must not change the record.');
    }

    public function testStaffCannotDeleteLegacyCreditRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff2b@test.local', ['ROLE_STAFF'], $bureau);
        $credit = $this->makeCredit($bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/credit/' . $credit->getId());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $deletedAt = $this->em->getConnection()->fetchOne('SELECT deleted_at FROM credit WHERE id = ?', [$credit->getId()]);
        $this->assertNull($deletedAt, 'Denied delete must not soft-delete the record.');
    }

    public function testStaffCanStillReadLegacyCreditList(): void
    {
        // Read access is intentionally untouched this phase.
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff3@test.local', ['ROLE_STAFF'], $bureau);
        $this->makeCredit($bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('GET', '/api/credit');
        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── 2. AchatVoitureController -- create() deliberately left open ──

    public function testStaffCanCreateAVehiclePurchaseRecordByDesign(): void
    {
        // Confirmed: voiture-create.component.ts (a ROLE_STAFF-reachable
        // page) calls this directly when registering a new vehicle.
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff4@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'ACH-STAFF-0001');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/achat-voiture', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateAchat' => '2026-01-01', 'prixAchat' => 150000, 'typeFinancement' => 'comptant',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    private function makeAchatVoiture(\App\Entity\Bureau $bureau, string $plate): AchatVoiture
    {
        $voiture = $this->makeVoiture($bureau, $plate);
        $achat = new AchatVoiture();
        $achat->setVoiture($voiture);
        $achat->setDateAchat(new \DateTimeImmutable('today'));
        $achat->setPrixAchat('150000.00');
        $achat->setTypeFinancement('comptant');
        $achat->setStatut('actif');
        $achat->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($achat);
        $this->em->flush();
        return $achat;
    }

    public function testStaffCannotUpdateAVehiclePurchaseRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff5@test.local', ['ROLE_STAFF'], $bureau);
        $achat  = $this->makeAchatVoiture($bureau, 'ACH-STAFF-0002');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('PUT', '/api/achat-voiture/' . $achat->getId(), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['notes' => 'x']));
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $notes = $this->em->getConnection()->fetchOne('SELECT notes FROM achat_voiture WHERE id = ?', [$achat->getId()]);
        $this->assertNull($notes, 'Denied update must not change the record.');
    }

    public function testStaffCannotDeleteAVehiclePurchaseRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff5b@test.local', ['ROLE_STAFF'], $bureau);
        $achat  = $this->makeAchatVoiture($bureau, 'ACH-STAFF-0003');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/achat-voiture/' . $achat->getId());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $deletedAt = $this->em->getConnection()->fetchOne('SELECT deleted_at FROM achat_voiture WHERE id = ?', [$achat->getId()]);
        $this->assertNull($deletedAt, 'Denied delete must not soft-delete the record.');
    }

    // ── 3. VenteController -- create() deliberately left open ──

    public function testStaffCanRecordAVehicleSaleByDesign(): void
    {
        // Confirmed: voiture.service.ts::recordSale(), called from
        // voiture-detail.component.ts (ROLE_STAFF-reachable).
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff6@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'VTE-STAFF-0001');
        // A sale can only be completed once the vehicle is in the
        // DECOMMISSIONED lifecycle state (FleetLifecycleManager::assertCanCompleteSale).
        // VoitureStatusWriteGuard forbids writing this via the entity setter
        // outside FleetLifecycleManager::applyEvent() -- raw SQL for this
        // fixture-only state change is the deliberate bypass.
        $this->em->getConnection()->executeStatement(
            "UPDATE voiture SET voiture_status = 'decommissioned' WHERE id = ?",
            [$voiture->getId()]
        );
        $this->em->refresh($voiture);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/vente', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateVente' => '2026-01-01', 'prixVente' => 90000,
        ]));

        // Phase 12.5 found this path independently 500s for every role (a
        // DQL-UPDATE-with-JOIN bug in FleetLifecycleManager, unrelated to
        // authorization) and could only assert "not a 403" here. Phase 12.6
        // fixed that bug (see tests/Fleet/VehicleSaleCompletionTest.php for
        // full coverage) -- this now asserts the real, intended outcome.
        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    private function makeVente(\App\Entity\Bureau $bureau, string $plate): Vente
    {
        $voiture = $this->makeVoiture($bureau, $plate);
        $vente = new Vente();
        $vente->setVoiture($voiture);
        $vente->setDateVente(new \DateTimeImmutable('today'));
        $vente->setPrixVente('90000.00');
        $vente->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($vente);
        $this->em->flush();
        return $vente;
    }

    public function testStaffCannotUpdateAVehicleSaleRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff7@test.local', ['ROLE_STAFF'], $bureau);
        $vente  = $this->makeVente($bureau, 'VTE-STAFF-0002');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('PUT', '/api/vente/' . $vente->getId(), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['prixVente' => 1]));
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $prix = $this->em->getConnection()->fetchOne('SELECT prix_vente FROM vente WHERE id = ?', [$vente->getId()]);
        $this->assertSame('90000.00', $prix, 'Denied update must not change the record.');
    }

    public function testStaffCannotDeleteAVehicleSaleRecord(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff7b@test.local', ['ROLE_STAFF'], $bureau);
        $vente  = $this->makeVente($bureau, 'VTE-STAFF-0003');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/vente/' . $vente->getId());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $deletedAt = $this->em->getConnection()->fetchOne('SELECT deleted_at FROM vente WHERE id = ?', [$vente->getId()]);
        $this->assertNull($deletedAt, 'Denied delete must not soft-delete the record.');
    }

    // ── 4. FinancialInstitutionController -- fully restricted ──

    public function testStaffCannotCreateAFinancialInstitution(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff8@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/financial-institution', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['name' => 'Bank X']));
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM financial_institution'));
    }

    public function testStaffCannotDeleteAFinancialInstitution(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff8b@test.local', ['ROLE_STAFF'], $bureau);

        $fi = new FinancialInstitution();
        $fi->setName('Bank Y');
        $fi->setType('bank');
        $fi->setStatus('active');
        $fi->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($fi);
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/financial-institution/' . $fi->getId());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $status = $this->em->getConnection()->fetchOne('SELECT status FROM financial_institution WHERE id = ?', [$fi->getId()]);
        $this->assertSame('active', $status, 'Denied delete (soft, via status) must not change the record.');
    }

    public function testManagerCanCreateAFinancialInstitution(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $manager = $this->makeUser('manager2@test.local', ['ROLE_MANAGER'], $bureau);

        $this->httpClient->loginUser($manager);
        $this->httpClient->request('POST', '/api/financial-institution', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['name' => 'Bank X']));
        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── 5. AchatInstallmentController -- pay/update/delete restricted ──

    public function testStaffCannotPayAVehiclePurchaseInstallment(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff9@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'INST-0001');

        $achat = new AchatVoiture();
        $achat->setVoiture($voiture);
        $achat->setDateAchat(new \DateTimeImmutable('today'));
        $achat->setPrixAchat('150000.00');
        $achat->setTypeFinancement('mensualites');
        $achat->setStatut('actif');
        $achat->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($achat);
        $this->em->flush();

        $inst = new AchatInstallment();
        $inst->setAchatVoiture($achat);
        $inst->setInstallmentNumber(1);
        $inst->setDueDate(new \DateTimeImmutable('+1 month'));
        $inst->setAmount('5000.00');
        $inst->setAmountPaid('0.00');
        $inst->setStatus('pending');
        $inst->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($inst);
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/achat-installment/' . $inst->getId() . '/pay', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['amountPaid' => 5000]));
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $row = $this->em->getConnection()->fetchAssociative('SELECT amount_paid, status FROM achat_installment WHERE id = ?', [$inst->getId()]);
        $this->assertSame('0.00', $row['amount_paid'], 'Denied payment must not change the installment.');
        $this->assertSame('pending', $row['status']);
    }

    // ── 6. VehicleCreditDocumentController -- upload/delete restricted ──

    public function testStaffCannotUploadACreditDocument(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff10@test.local', ['ROLE_STAFF'], $bureau);
        $credit  = $this->makeVehicleCredit($bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/vehicle-credit-document', server: [], content: null, parameters: ['vehicleCreditId' => $credit->getId()]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vehicle_credit_document'));
    }

    public function testStaffCannotDeleteACreditDocument(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff10b@test.local', ['ROLE_STAFF'], $bureau);
        $credit  = $this->makeVehicleCredit($bureau);

        $doc = new VehicleCreditDocument();
        $doc->setVehicleCredit($credit);
        $doc->setDocumentType('contract');
        $doc->setFilePath('/uploads/credit-documents/none.pdf');
        $doc->setFileName('none.pdf');
        $this->em->persist($doc);
        $this->em->flush();
        $docId = $doc->getId();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/vehicle-credit-document/' . $docId);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vehicle_credit_document WHERE id = ?', [$docId]), 'Denied delete must not remove the document row.');
    }

    // ── 7. VehicleCreditReminderController -- markSent/delete restricted ──

    public function testStaffCannotMarkOrDeleteACreditReminder(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff11@test.local', ['ROLE_STAFF'], $bureau);
        $credit = $this->makeVehicleCredit($bureau);

        $reminder = new VehicleCreditReminder();
        $reminder->setVehicleCredit($credit);
        $reminder->setReminderDate(new \DateTimeImmutable('today'));
        $reminder->setReminderType('due_today');
        $reminder->setStatus('pending');
        $this->em->persist($reminder);
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('PUT', '/api/vehicle-credit-reminder/' . $reminder->getId() . '/mark-sent');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $status = $this->em->getConnection()->fetchOne('SELECT status FROM vehicle_credit_reminder WHERE id = ?', [$reminder->getId()]);
        $this->assertSame('pending', $status);
    }

    // ── 8. VehicleCreditPaymentController -- create/delete confirmed left
    //    open (regression check); attachment actions' pure-ADMIN bugfix ──

    public function testPureAdminAccountCanUploadAPaymentAttachment(): void
    {
        // Before this phase, 'ROLE_MANAGER' alone would have wrongly denied
        // this -- an admin-only account has no ROLE_MANAGER and there is no
        // role hierarchy.
        $bureau  = $this->makeBureau('Bureau A');
        $admin   = $this->makeUser('admin2@test.local', ['ROLE_ADMIN'], null);
        $voiture = $this->makeVoiture($bureau, 'PAY-ADM-0001');
        $credit  = $this->makeVehicleCredit($bureau, $voiture);

        $payment = new VehicleCreditPayment();
        $payment->setVehicleCredit($credit);
        $payment->setPaymentType('scheduled');
        $payment->setPaymentMethod('bank_transfer');
        $payment->setAmount('5000.00');
        $payment->setPaymentDate(new \DateTimeImmutable('today'));
        $this->em->persist($payment);
        $this->em->flush();

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('DELETE', '/api/vehicle-credit-payment/attachment/999999');
        // 999999 doesn't exist -> 404, but the key proof is it's NOT 403:
        // the admin cleared the role check and reached the controller body.
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testStaffWithOnlyRoleManagerStillCannotBypassFirewall(): void
    {
        // Sanity check unrelated to the ROLE_MANAGER-alone bug: a client-role
        // account never gets ROLE_USER and so never passes the firewall.
        $client = $this->makeUser('client2@test.local', ['ROLE_CLIENT'], null);
        $this->httpClient->loginUser($client);
        $this->httpClient->request('GET', '/api/vehicle-credit-payment');
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── 9. PaiementDepenseController -- confirmed NOT restricted (regression) ──

    public function testStaffCanStillCreateAnExpensePaymentByDesign(): void
    {
        // Confirmed: pay-dep-panel.component.ts is embedded in voiture-detail's
        // financial-tab/damage-tab (ROLE_STAFF-reachable). Must remain open.
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff12@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'DEP-0001');

        $depense = new \App\Entity\Depense();
        $depense->setDateDebut(new \DateTimeImmutable('today'));
        $depense->setTypeDepense('reparation');
        $depense->setDescription('Test');
        $depense->setMontant('1000.00');
        $depense->setStatut(\App\Enum\StatusEnum::EN_ATTENTE);
        $depense->setVoiture($voiture);
        $depense->setBureau($bureau);
        $depense->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/paiement-depense', server: [], content: null, parameters: [
            'depenseId' => $depense->getId(), 'montant' => 500,
        ]);

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── 10. VehicleReturnInspectionController -- confirmed NOT restricted ──

    public function testStaffCanStillCreateAReturnInspectionByDesign(): void
    {
        // Confirmed via app.routes.ts: /return-inspection's OWN route guard
        // is ['ROLE_ADMIN','ROLE_MANAGER','ROLE_STAFF'], contradicting the
        // sidebar's ADMIN-only MENU visibility. The route guard is the real
        // access control. Phase 12.4's "align to ADMIN-only" recommendation
        // is REVERSED here after independent verification.
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff13@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'VRI-0001');
        $client  = $this->makeClientEntity('VRI Client');
        $reservation = $this->makeReservation($voiture, $client, 'en_cours');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/vehicle-return-inspection', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'reservationId' => $reservation->getId(),
        ]));

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Helper ──

    private function makeVehicleCredit(\App\Entity\Bureau $bureau, ?\App\Entity\Voiture $voiture = null): VehicleCredit
    {
        $voiture ??= $this->makeVoiture($bureau, 'VC-' . uniqid());
        $credit = new VehicleCredit();
        $credit->setVoiture($voiture);
        $credit->setVehiclePrice('200000.00');
        $credit->setFinancedAmount('180000.00');
        $credit->setRemainingBalance('180000.00');
        $credit->setMonthlyInstallment('5000.00');
        $credit->setDurationMonths(36);
        $credit->setTotalCost('200000.00');
        $credit->setStatus('active');
        $credit->setStartDate(new \DateTimeImmutable('today'));
        $credit->setEndDate(new \DateTimeImmutable('+36 months'));
        $credit->setFirstPaymentDate(new \DateTimeImmutable('+1 month'));
        $credit->setDueDay(1);
        $credit->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($credit);
        $this->em->flush();
        return $credit;
    }
}
