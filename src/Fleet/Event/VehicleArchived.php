<?php

namespace App\Fleet\Event;

readonly class VehicleArchived implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $archivedBy,
        private ?string $reason,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getArchivedBy(): int { return $this->archivedBy; }
    public function getReason(): ?string { return $this->reason; }
    public function getEventName(): string { return 'vehicle.archived'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
