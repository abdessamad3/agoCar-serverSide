<?php

namespace App\Fleet\Event;

readonly class VehiclePurchaseRecorded implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $achatId,
        private ?int $fournisseurId,
        private float $prixAchat,
        private string $typeFinancement,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getAchatId(): int { return $this->achatId; }
    public function getFournisseurId(): ?int { return $this->fournisseurId; }
    public function getPrixAchat(): float { return $this->prixAchat; }
    public function getTypeFinancement(): string { return $this->typeFinancement; }
    public function getEventName(): string { return 'vehicle.purchase_recorded'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
