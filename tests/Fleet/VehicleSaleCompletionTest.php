<?php

namespace App\Tests\Fleet;

use App\Entity\Assurance;
use App\Entity\Depense;
use App\Entity\Reparation;
use App\Enum\StatusEnum;
use App\Tests\Api\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.6 Stage 5: regression coverage for the vehicle-sale-completion
 * bug (FleetLifecycleManager's VehicleSold handler used a JOIN inside a DQL
 * UPDATE, which DQL does not support -- always threw, for every role).
 * Covers the HTTP flow end to end, not just the isolated service call used
 * during root-cause investigation.
 */
final class VehicleSaleCompletionTest extends ApiTestCase
{
    private function decommission(\App\Entity\Voiture $voiture): void
    {
        // VoitureStatusWriteGuard forbids writing voiture_status via the
        // entity setter outside FleetLifecycleManager::applyEvent() -- raw
        // SQL is the deliberate, test-only bypass for this fixture state.
        // The refresh() is required: without it the subsequent HTTP request
        // in this same test observed the pre-update status (established
        // pattern from Phase 12.5's equivalent fixture).
        $this->em->getConnection()->executeStatement(
            "UPDATE voiture SET voiture_status = 'decommissioned' WHERE id = ?",
            [$voiture->getId()]
        );
        $this->em->refresh($voiture);
    }

    private function makeActiveInsurance(\App\Entity\Voiture $voiture): Assurance
    {
        $depense = new Depense();
        $depense->setDateDebut(new \DateTimeImmutable('-6 months'));
        $depense->setTypeDepense('assurance');
        $depense->setMontant('3000.00');
        $depense->setStatut(StatusEnum::PAYEE);
        $depense->setVoiture($voiture);
        $depense->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);

        $assurance = new Assurance();
        $assurance->setDepense($depense);
        $assurance->setCompagnie('Test Insurer');
        $assurance->setTypeAssurance('tous_risques');
        $assurance->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($assurance);
        $this->em->flush();

        return $assurance;
    }

    // ── 1. Sale completion when permitted ──────────────────────────────────

    public function testSaleCompletesSuccessfullyForADecommissionedVehicle(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $admin   = $this->makeUser('admin@test.local', ['ROLE_ADMIN'], null);
        $voiture = $this->makeVoiture($bureau, 'SALE-OK-0001');
        $this->decommission($voiture);
        $insurance = $this->makeActiveInsurance($voiture);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('POST', '/api/vente', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateVente' => '2026-01-01', 'prixVente' => 90000,
        ]));

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());

        $venteCount = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vente WHERE voiture_id = ?', [$voiture->getId()]);
        $this->assertSame(1, $venteCount, 'A vente row must have been created.');

        $voitureStatus = $this->em->getConnection()->fetchOne('SELECT voiture_status FROM voiture WHERE id = ?', [$voiture->getId()]);
        $this->assertSame('vendu', $voitureStatus, 'Vehicle must transition to SOLD after a completed sale.');

        $archivedAt = $this->em->getConnection()->fetchOne('SELECT archived_at FROM assurance WHERE id = ?', [$insurance->getId()]);
        $this->assertNotNull($archivedAt, 'Active insurance tied to the sold vehicle must be archived (the side effect that was crashing before this fix).');
    }

    // ── 2. Sale rejection when open repairs exist (preserved business rule) ──

    public function testSaleIsRejectedWhenAnOpenRepairExists(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $admin   = $this->makeUser('admin2@test.local', ['ROLE_ADMIN'], null);
        $voiture = $this->makeVoiture($bureau, 'SALE-REPAIR-0001');
        $this->decommission($voiture);

        $depense = new Depense();
        $depense->setDateDebut(new \DateTimeImmutable('-1 week'));
        $depense->setDateFin(null); // still open
        $depense->setTypeDepense('reparation');
        $depense->setMontant('1500.00');
        $depense->setStatut(StatusEnum::EN_ATTENTE);
        $depense->setVoiture($voiture);
        $depense->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($depense);

        $reparation = new Reparation();
        $reparation->setDepense($depense);
        $reparation->setDescriptionTechnique('Engine repair in progress');
        $reparation->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reparation);
        $this->em->flush();

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('POST', '/api/vente', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateVente' => '2026-01-01', 'prixVente' => 90000,
        ]));

        // ── 3. Correct error response ──
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertStringContainsString('open repair', $body['error'] ?? '');

        // ── 4. No unintended database changes on failure ──
        $venteCount = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vente WHERE voiture_id = ?', [$voiture->getId()]);
        $this->assertSame(0, $venteCount, 'A rejected sale must not create a vente row.');

        $voitureStatus = $this->em->getConnection()->fetchOne('SELECT voiture_status FROM voiture WHERE id = ?', [$voiture->getId()]);
        $this->assertSame('decommissioned', $voitureStatus, 'A rejected sale must not change the vehicle status.');
    }

    public function testSaleIsRejectedWhenVehicleIsNotDecommissioned(): void
    {
        // Preserves the other existing lifecycle-transition rule: a vehicle
        // still 'disponible' cannot be sold directly.
        $bureau  = $this->makeBureau('Bureau A');
        $admin   = $this->makeUser('admin3@test.local', ['ROLE_ADMIN'], null);
        $voiture = $this->makeVoiture($bureau, 'SALE-NOTDECOM-0001');
        // Deliberately NOT decommissioned -- stays 'disponible' from makeVoiture().

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('POST', '/api/vente', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateVente' => '2026-01-01', 'prixVente' => 90000,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->httpClient->getResponse()->getStatusCode());

        $venteCount = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vente WHERE voiture_id = ?', [$voiture->getId()]);
        $this->assertSame(0, $venteCount);
    }

    public function testSaleIsRejectedForAZeroOrNegativePrice(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $admin   = $this->makeUser('admin4@test.local', ['ROLE_ADMIN'], null);
        $voiture = $this->makeVoiture($bureau, 'SALE-ZEROPRICE-0001');
        $this->decommission($voiture);

        $this->httpClient->loginUser($admin);
        $this->httpClient->request('POST', '/api/vente', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateVente' => '2026-01-01', 'prixVente' => 0,
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->httpClient->getResponse()->getStatusCode());
    }

    // ── Legitimate staff permission preserved (Phase 12.5 finding, re-verified) ──

    public function testStaffCanStillCompleteASaleEndToEnd(): void
    {
        $bureau  = $this->makeBureau('Bureau A');
        $staff   = $this->makeUser('staff@test.local', ['ROLE_STAFF'], $bureau);
        $voiture = $this->makeVoiture($bureau, 'SALE-STAFF-0001');
        $this->decommission($voiture);

        $this->httpClient->loginUser($staff);
        $this->httpClient->request('POST', '/api/vente', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'voitureId' => $voiture->getId(), 'dateVente' => '2026-01-01', 'prixVente' => 90000,
        ]));

        $this->assertSame(
            Response::HTTP_CREATED,
            $this->httpClient->getResponse()->getStatusCode(),
            'ROLE_STAFF must retain the ability to record a sale (confirmed in Phase 12.5 via voiture.service.ts::recordSale) -- now that the underlying bug is fixed, this proves end to end, not just "not a 403".'
        );
    }
}
