<?php

namespace App\Fleet\Event;

/**
 * Marker interface for all events consumed by FleetLifecycleManager.
 * Every write operation that can affect a vehicle's lifecycle state
 * must produce a FleetEvent and pass it to FleetLifecycleManager::applyEvent().
 */
interface FleetEvent
{
    public function getVoitureId(): int;
    public function getEventName(): string;
    public function occurredAt(): \DateTimeImmutable;
}
