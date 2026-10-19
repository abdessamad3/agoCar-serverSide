<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.9 QA pass: ReservationRepository::hasConfirmedOverlap() -- the
 * double-booking guard on PublicBookingController::create() -- had zero test
 * coverage anywhere in this suite before now, despite being the mechanism
 * that prevents two customers from being promised the same vehicle on
 * overlapping dates. Confirmed by code reading (src/Repository/
 * ReservationRepository.php:196) that only 'confirmed'/'en_cours' statuses
 * block a new request -- 'pending' deliberately does not, so staff can hold
 * multiple pending requests on the same slot before confirming one.
 */
final class ReservationOverlapTest extends ApiTestCase
{
    private function bookingPayload(int $carId, string $debut, string $fin): array
    {
        return [
            'carId' => $carId,
            'dateDebut' => $debut,
            'dateFin' => $fin,
            'fullName' => 'Overlap Test',
            'phone' => '0600000000',
            'email' => 'overlap-test@test.local',
            'permisDelivreLe' => '2020-01-01',
            'idNumber' => 'OVERLAP' . random_int(1000, 9999),
            'idType' => 'cin',
        ];
    }

    public function testOverlappingConfirmedReservationBlocksNewBooking(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'OVL-001');
        $client = $this->makeClientEntity('Existing Client');
        $existing = $this->makeReservation($voiture, $client, 'confirmed');
        $existing->setDateDebut(new \DateTimeImmutable('+5 days'));
        $existing->setDateFin(new \DateTimeImmutable('+8 days'));
        $this->em->flush();

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload(
                $voiture->getId(),
                (new \DateTimeImmutable('+6 days'))->format('Y-m-d\TH:i'),
                (new \DateTimeImmutable('+7 days'))->format('Y-m-d\TH:i')
            ))
        );

        $this->assertSame(Response::HTTP_CONFLICT, $this->httpClient->getResponse()->getStatusCode());
        $body = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertSame('CAR_NOT_AVAILABLE', $body['error']);
    }

    public function testOverlappingPendingReservationDoesNotBlockNewBooking(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'OVL-002');
        $client = $this->makeClientEntity('Existing Pending Client');
        $existing = $this->makeReservation($voiture, $client, 'pending');
        $existing->setDateDebut(new \DateTimeImmutable('+5 days'));
        $existing->setDateFin(new \DateTimeImmutable('+8 days'));
        $this->em->flush();

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload(
                $voiture->getId(),
                (new \DateTimeImmutable('+6 days'))->format('Y-m-d\TH:i'),
                (new \DateTimeImmutable('+7 days'))->format('Y-m-d\TH:i')
            ))
        );

        $this->assertSame(
            Response::HTTP_CREATED,
            $this->httpClient->getResponse()->getStatusCode(),
            'A pending reservation must never block another customer from requesting the same slot -- staff decide which one to confirm.'
        );
    }

    public function testNonOverlappingDatesOnSameVehicleSucceed(): void
    {
        $bureau = $this->makeBureau('Bureau A');
        $voiture = $this->makeVoiture($bureau, 'OVL-003');
        $client = $this->makeClientEntity('Existing Client 2');
        $existing = $this->makeReservation($voiture, $client, 'confirmed');
        $existing->setDateDebut(new \DateTimeImmutable('+5 days'));
        $existing->setDateFin(new \DateTimeImmutable('+8 days'));
        $this->em->flush();

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload(
                $voiture->getId(),
                (new \DateTimeImmutable('+10 days'))->format('Y-m-d\TH:i'),
                (new \DateTimeImmutable('+12 days'))->format('Y-m-d\TH:i')
            ))
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }

    public function testOverlapOnDifferentVehicleDoesNotBlock(): void
    {
        $bureau   = $this->makeBureau('Bureau A');
        $voitureA = $this->makeVoiture($bureau, 'OVL-004A');
        $voitureB = $this->makeVoiture($bureau, 'OVL-004B');
        $client   = $this->makeClientEntity('Existing Client 3');
        $existing = $this->makeReservation($voitureA, $client, 'confirmed');
        $existing->setDateDebut(new \DateTimeImmutable('+5 days'));
        $existing->setDateFin(new \DateTimeImmutable('+8 days'));
        $this->em->flush();

        $this->httpClient->request(
            'POST',
            '/api/site/booking-requests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->bookingPayload(
                $voitureB->getId(),
                (new \DateTimeImmutable('+6 days'))->format('Y-m-d\TH:i'),
                (new \DateTimeImmutable('+7 days'))->format('Y-m-d\TH:i')
            ))
        );

        $this->assertSame(Response::HTTP_CREATED, $this->httpClient->getResponse()->getStatusCode());
    }
}
