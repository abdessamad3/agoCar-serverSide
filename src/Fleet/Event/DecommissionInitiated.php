<?php

namespace App\Fleet\Event;

readonly class DecommissionInitiated implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $initiatedBy,
        private ?string $reason,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getInitiatedBy(): int { return $this->initiatedBy; }
    public function getReason(): ?string { return $this->reason; }
    public function getEventName(): string { return 'vehicle.decommission_initiated'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
