<?php

namespace App\Fleet\Event;

readonly class TemporalSyncTriggered implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private ?int $bureauId = null,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getBureauId(): ?int { return $this->bureauId; }
    public function getEventName(): string { return 'temporal.sync_triggered'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
