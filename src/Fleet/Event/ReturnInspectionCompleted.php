<?php

namespace App\Fleet\Event;

readonly class ReturnInspectionCompleted implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $reservationId,
        private int $inspectionId,
        private int $kilometrage,
        private string $fuelLevelIn,
        private float $fuelCharge,
        private float $lateCharge,
        private float $damageCharge,
        private float $equipmentCharge,
        private string $condition = 'clean',
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getReservationId(): int { return $this->reservationId; }
    public function getInspectionId(): int { return $this->inspectionId; }
    public function getKilometrage(): int { return $this->kilometrage; }
    public function getFuelLevelIn(): string { return $this->fuelLevelIn; }
    public function getFuelCharge(): float { return $this->fuelCharge; }
    public function getLateCharge(): float { return $this->lateCharge; }
    public function getDamageCharge(): float { return $this->damageCharge; }
    public function getEquipmentCharge(): float { return $this->equipmentCharge; }
    public function getCondition(): string { return $this->condition; }
    public function hasDamage(): bool { return $this->condition !== 'clean'; }
    public function hasDamageCharges(): bool { return $this->damageCharge > 0; }
    public function getTotalAdditionalCharges(): float
    {
        return $this->fuelCharge + $this->lateCharge + $this->damageCharge + $this->equipmentCharge;
    }
    public function getEventName(): string { return 'return_inspection.completed'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
