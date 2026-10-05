<?php

namespace App\Fleet\Event;

readonly class InsuranceCreated implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $assuranceId,
        private \DateTimeImmutable $dateDebut,
        private \DateTimeImmutable $dateFin,
        private string $compagnie,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getAssuranceId(): int { return $this->assuranceId; }
    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }
    public function getDateFin(): \DateTimeImmutable { return $this->dateFin; }
    public function getCompagnie(): string { return $this->compagnie; }
    public function getEventName(): string { return 'insurance.created'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
