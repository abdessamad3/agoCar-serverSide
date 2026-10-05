<?php

namespace App\Fleet\Event;

readonly class RepairCompleted implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $reparationId,
        private \DateTimeImmutable $dateFin,
        private float $totalCost,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getReparationId(): int { return $this->reparationId; }
    public function getDateFin(): \DateTimeImmutable { return $this->dateFin; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getEventName(): string { return 'repair.completed'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
