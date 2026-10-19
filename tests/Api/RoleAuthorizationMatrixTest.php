<?php

namespace App\Tests\Api;

use App\Entity\VehicleCredit;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.4 Stage 3: whether a lower-privilege (bare ROLE_STAFF) account can
 * perform the six sensitive operations named in the audit request. Each test
 * documents what CURRENTLY happens -- some assert a 403 (already correctly
 * restricted), others assert the action currently SUCCEEDS (a confirmed gap,
 * reported for a decision, not fixed in this audit-only phase).
 */
final class RoleAuthorizationMatrixTest extends ApiTestCase
{
    // 1. Seasonal pricing -- TarifSaisonnierController::assertCanManage()
    // already restricts create/update/delete to ROLE_ADMIN/ROLE_MANAGER.
    public function testStaffCannotCreateSeasonalPricingRule(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'POST',
            '/api/tarif-saisonnier',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'libelle' => 'Été', 'dateDebut' => '2026-06-01', 'dateFin' => '2026-08-31', 'valeur' => 15,
            ])
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testManagerCanCreateSeasonalPricingRule(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $manager = $this->makeUser('manager@test.local', ['ROLE_MANAGER'], $bureau);

        $this->httpClient->loginUser($manager);
        $this->httpClient->request(
            'POST',
            '/api/tarif-saisonnier',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'libelle' => 'Été', 'dateDebut' => '2026-06-01', 'dateFin' => '2026-08-31', 'valeur' => 15,
            ])
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    // 2. Financial records -- Phase 12.4 flagged this as an unrestricted gap;
    // Phase 12.5 independently verified (via voiture-detail.component.ts /
    // voiture.service.ts) that ROLE_STAFF legitimately pays/cancels a vehicle
    // credit installment from the ROLE_STAFF-reachable /voiture/:id page. This
    // is confirmed INTENTIONAL staff-facing functionality, not a gap -- left
    // unrestricted deliberately. See RoleAuthorizationMatrixPhase125Test.php
    // for the controllers that WERE restricted this phase.
    public function testStaffCanCreateAFinancialPaymentRecordByDesign(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff2@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'CREDIT-0001');

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

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'POST',
            '/api/vehicle-credit-payment',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['vehicleCreditId' => $credit->getId(), 'amount' => 5000])
        );

        $this->assertSame(
            Response::HTTP_CREATED,
            $this->httpClient->getResponse()->getStatusCode(),
            'Confirmed intentional: ROLE_STAFF pays installments from the vehicle detail page.'
        );
    }

    // 3. Merge duplicate customers -- ClientDuplicateController::resolve()
    // already restricts the 'merge' action to ROLE_ADMIN/ROLE_MANAGER.
    public function testStaffCannotMergeDuplicateClients(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff3@test.local', ['ROLE_STAFF'], $bureau);

        $original  = $this->makeClientEntity('Original Client');
        $duplicate = $this->makeClientEntity('Duplicate Client');
        $duplicate->setPossibleDuplicateOf($original);
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'POST',
            '/api/client-duplicates/' . $duplicate->getId() . '/resolve',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['action' => 'merge'])
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testStaffCanDismissADuplicateFlagByDesign(): void
    {
        // Not a gap -- dismiss (unlike merge) is intentionally open to any
        // staff tier per the controller's own documented design.
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff4@test.local', ['ROLE_STAFF'], $bureau);

        $original  = $this->makeClientEntity('Original Client 2');
        $duplicate = $this->makeClientEntity('Duplicate Client 2');
        $duplicate->setPossibleDuplicateOf($original);
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'POST',
            '/api/client-duplicates/' . $duplicate->getId() . '/resolve',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['action' => 'dismiss'])
        );

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }

    // 4. Sensitive user information -- UtilisateurController::show()'s
    // signatureBlob restriction, fixed in Phase 12.2. Re-verified here as a
    // regression check for this phase.
    public function testStaffCannotSeeAColleaguesSignatureImageViaShow(): void
    {
        $bureau    = $this->makeBureau('Bureau A');
        $staff     = $this->makeUser('staff5@test.local', ['ROLE_STAFF'], $bureau);
        $colleague = $this->makeUser('colleague2@test.local', ['ROLE_MANAGER'], $bureau);
        $colleague->setSignatureBlob('data:image/png;base64,ZZZZ');
        $this->em->flush();

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('GET', '/api/utilisateur/' . $colleague->getId());

        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertNull($data['signatureBlob']);
    }

    // 5. Modify another user's roles -- UtilisateurController::update()
    // already restricts the 'roles' field to admins.
    public function testStaffCannotChangeAnotherUsersRoles(): void
    {
        $bureau   = $this->makeBureau('Bureau A');
        $staff    = $this->makeUser('staff6@test.local', ['ROLE_STAFF'], $bureau);
        $colleague = $this->makeUser('colleague3@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'PUT',
            '/api/utilisateur/' . $colleague->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['roles' => ['ROLE_ADMIN']])
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());

        $this->em->refresh($colleague);
        $this->assertNotContains('ROLE_ADMIN', $colleague->getRoles(), 'Roles must be unchanged after a forbidden request.');
    }

    public function testStaffCannotEscalateTheirOwnRoles(): void
    {
        // A user CAN edit their own record (self-service profile fields),
        // but the 'roles' field itself must still require admin even when
        // editing yourself -- otherwise self-service becomes self-promotion.
        $bureau = $this->makeBureau('Bureau A');
        $staff  = $this->makeUser('staff7@test.local', ['ROLE_STAFF'], $bureau);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request(
            'PUT',
            '/api/utilisateur/' . $staff->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['roles' => ['ROLE_ADMIN']])
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }

    // 6. Administrator-only operations -- spot-check a representative one
    // (vehicle deletion; UtilisateurController::create() already covered by
    // AuthorizationBoundaryTest from Phase 12.1).
    public function testStaffCannotDeleteAVehicle(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff8@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'DELTEST-0001');

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('DELETE', '/api/voiture/' . $voiture->getId());

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->httpClient->getResponse()->getStatusCode());
    }
}
