<?php

namespace App\Fleet\Event;

readonly class RentalStarted implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $reservationId,
        private int $deliveryId,
        private int $mileageOut,
        private string $fuelLevelOut,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getReservationId(): int { return $this->reservationId; }
    public function getDeliveryId(): int { return $this->deliveryId; }
    public function getMileageOut(): int { return $this->mileageOut; }
    public function getFuelLevelOut(): string { return $this->fuelLevelOut; }
    public function getEventName(): string { return 'rental.started'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
