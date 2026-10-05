<?php

namespace App\Fleet\Event;

readonly class InspectionExpired implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $suiviId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getSuiviId(): int { return $this->suiviId; }
    public function getEventName(): string { return 'inspection.expired'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
