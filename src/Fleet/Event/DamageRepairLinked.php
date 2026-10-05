<?php

namespace App\Fleet\Event;

readonly class DamageRepairLinked implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $damageId,
        private int $reparationId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getDamageId(): int { return $this->damageId; }
    public function getReparationId(): int { return $this->reparationId; }
    public function getEventName(): string { return 'damage.repair_linked'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
