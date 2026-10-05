<?php

namespace App\Fleet\Event;

readonly class DamageRecorded implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $damageId,
        private string $zone,
        private string $severity,
        private string $source, // 'delivery' | 'return' | 'standalone'
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getDamageId(): int { return $this->damageId; }
    public function getZone(): string { return $this->zone; }
    public function getSeverity(): string { return $this->severity; }
    public function getSource(): string { return $this->source; }
    public function isSignificant(): bool
    {
        return in_array($this->severity, ['crack', 'broken'], true);
    }
    public function getEventName(): string { return 'damage.recorded'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
