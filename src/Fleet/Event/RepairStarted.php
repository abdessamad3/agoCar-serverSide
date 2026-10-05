<?php

namespace App\Fleet\Event;

readonly class RepairStarted implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $reparationId,
        private \DateTimeImmutable $dateDebut,
        private ?\DateTimeImmutable $dateFin,
        private ?string $categorie,
        private ?int $damageId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getReparationId(): int { return $this->reparationId; }
    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function getDamageId(): ?int { return $this->damageId; }
    public function isActiveToday(): bool
    {
        $today = new \DateTimeImmutable('today');
        return $this->dateDebut <= $today && ($this->dateFin === null || $this->dateFin >= $today);
    }
    public function getEventName(): string { return 'repair.started'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
