<?php

namespace App\Fleet\Event;

readonly class VehicleSetupStarted implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getEventName(): string { return 'vehicle.setup_started'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
