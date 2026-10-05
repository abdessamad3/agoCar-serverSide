<?php

namespace App\Fleet\Event;

readonly class VehicleDraftCreated implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getEventName(): string { return 'vehicle.draft_created'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
