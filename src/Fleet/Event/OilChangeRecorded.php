<?php

namespace App\Fleet\Event;

readonly class OilChangeRecorded implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $vidangeId,
        private int $kilometrageSuivant,
        private int $intervalleKm,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getVidangeId(): int { return $this->vidangeId; }
    public function getKilometrageSuivant(): int { return $this->kilometrageSuivant; }
    public function getIntervalleKm(): int { return $this->intervalleKm; }
    public function getEventName(): string { return 'oil_change.recorded'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
