<?php

namespace App\Fleet\Event;

readonly class VehicleSold implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $venteId,
        private \DateTimeImmutable $dateVente,
        private float $prixVente,
        private string $acheteurNom,
        private float $computedBenefice,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getVenteId(): int { return $this->venteId; }
    public function getDateVente(): \DateTimeImmutable { return $this->dateVente; }
    public function getPrixVente(): float { return $this->prixVente; }
    public function getAcheteurNom(): string { return $this->acheteurNom; }
    public function getComputedBenefice(): float { return $this->computedBenefice; }
    public function getEventName(): string { return 'vehicle.sold'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
