<?php

namespace App\Fleet\Event;

readonly class InsuranceExpired implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $assuranceId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getAssuranceId(): int { return $this->assuranceId; }
    public function getEventName(): string { return 'insurance.expired'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
