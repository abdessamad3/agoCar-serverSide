<?php

namespace App\Tests\Api;

use App\Entity\Bureau;
use App\Entity\Voiture;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.9 QA pass -- regression coverage for BUG-1: GET /api/site/voitures/categories
 * previously 500'd on production because the deployed PublicVoitureController.php
 * predated this route (no /categories handler, and /{id} had no numeric
 * requirement, so "categories" matched show(int $id) and crashed with a
 * TypeError). This file had zero coverage before now.
 */
final class PublicVoitureCategoriesTest extends ApiTestCase
{
    public function testCategoriesEndpointReturnsDistinctPublicCategories(): void
    {
        $bureau = $this->makeBureau('Bureau A');

        $v1 = $this->makeVoiture($bureau, 'CAT-001');
        $v1->setCategorie('economique');
        $v2 = $this->makeVoiture($bureau, 'CAT-002');
        $v2->setCategorie('luxe');
        $v3 = $this->makeVoiture($bureau, 'CAT-003');
        $v3->setCategorie('economique'); // duplicate category -- must not appear twice
        $this->em->flush();

        $this->httpClient->request('GET', '/api/site/voitures/categories');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertSame(['economique', 'luxe'], $body['data']);
    }

    public function testCategoriesEndpointExcludesNonPublicVehicles(): void
    {
        $bureau = $this->makeBureau('Bureau A');

        $visible = $this->makeVoiture($bureau, 'CAT-010');
        $visible->setCategorie('berline');

        $deleted = $this->makeVoiture($bureau, 'CAT-011');
        $deleted->setCategorie('suv');
        $deleted->setDeletedAt(new \DateTimeImmutable());

        // VoitureStatusWriteGuard forbids changing voitureStatus on an
        // already-persisted vehicle outside FleetLifecycleManager -- set the
        // non-public status before the first persist() instead (a plain
        // INSERT, not an UPDATE, so the guard doesn't apply).
        $unavailable = new Voiture();
        $unavailable->setMarque('Dacia');
        $unavailable->setModele('Sandero');
        $unavailable->setImmatriculation('CAT-012');
        $unavailable->setBureau($bureau);
        $unavailable->setAnnee(2024);
        $unavailable->setKilometrageActuel(15000);
        $unavailable->setTypeCarburant('essence');
        $unavailable->setClimatisation(true);
        $unavailable->setPrixJour('250.00');
        $unavailable->setVoitureStatus('vendue');
        $unavailable->setReservationStatus('disponible');
        $unavailable->setCreeAu(new \DateTimeImmutable());
        $unavailable->setCategorie('utilitaire');
        $this->em->persist($unavailable);

        $this->em->flush();

        $this->httpClient->request('GET', '/api/site/voitures/categories');

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertSame(['berline'], $body['data']);
    }

    public function testNonNumericIdDoesNotCrashShowAction(): void
    {
        // Regression for the exact production bug: before the fix, ANY
        // non-numeric segment under /api/site/voitures/{x} -- not just
        // "categories" -- would be routed into show(int $id) and crash with
        // a TypeError instead of a clean 404.
        $this->httpClient->request('GET', '/api/site/voitures/not-a-number');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testNumericIdStillResolvesCorrectly(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'CAT-020');
        $voiture->setCategorie('compacte');
        $this->em->flush();

        $this->httpClient->request('GET', '/api/site/voitures/' . $voiture->getId());

        $this->assertSame(Response::HTTP_OK, $this->httpClient->getResponse()->getStatusCode());
    }
}
