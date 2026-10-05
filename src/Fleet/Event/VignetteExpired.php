<?php

namespace App\Fleet\Event;

readonly class VignetteExpired implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $vignetteId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getVignetteId(): int { return $this->vignetteId; }
    public function getEventName(): string { return 'vignette.expired'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
