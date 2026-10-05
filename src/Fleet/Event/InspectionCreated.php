<?php

namespace App\Fleet\Event;

readonly class InspectionCreated implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $suiviId,
        private \DateTimeImmutable $dateReglages,
        private ?\DateTimeImmutable $dateFin,
        private ?string $resultat,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getSuiviId(): int { return $this->suiviId; }
    public function getDateReglages(): \DateTimeImmutable { return $this->dateReglages; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function getResultat(): ?string { return $this->resultat; }
    public function hasPassed(): bool { return $this->resultat === 'pass'; }
    public function getEventName(): string { return 'inspection.created'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
