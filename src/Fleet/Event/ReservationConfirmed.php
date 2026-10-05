<?php

namespace App\Fleet\Event;

readonly class ReservationConfirmed implements FleetEvent
{
    public function __construct(
        private int $voitureId,
        private int $reservationId,
        private int $clientId,
        private \DateTimeImmutable $dateDebut,
        private \DateTimeImmutable $dateFin,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getVoitureId(): int { return $this->voitureId; }
    public function getReservationId(): int { return $this->reservationId; }
    public function getClientId(): int { return $this->clientId; }
    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }
    public function getDateFin(): \DateTimeImmutable { return $this->dateFin; }
    public function startsToday(): bool
    {
        return $this->dateDebut->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d');
    }
    public function getEventName(): string { return 'reservation.confirmed'; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
