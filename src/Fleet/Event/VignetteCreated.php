<?php

namespace App\Fleet\Event;

readonly class VignetteCreated implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $vignetteId,
        private int $annee,
        private \DateTimeImmutable $dateLimite,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getVignetteId(): int { return $this->vignetteId; }
    public function getAnnee(): int { return $this->annee; }
    public function getDateLimite(): \DateTimeImmutable { return $this->dateLimite; }
    public function getEventName(): string { return 'vignette.created'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
