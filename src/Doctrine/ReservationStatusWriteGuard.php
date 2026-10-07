<?php

namespace App\Doctrine;

use App\Entity\Reservation;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

/**
 * Prevents any code from writing Reservation.reservationStatus directly.
 * Only ReservationLifecycleManager::transition() may change this field.
 *
 * Mirrors VoitureStatusWriteGuard. Initial status on a NEW reservation
 * (set before the first persist/flush) is unaffected — this only fires on
 * preUpdate, i.e. changes to an already-persisted row.
 *
 * Usage in ReservationLifecycleManager:
 *   $this->guard->activate();
 *   try { $this->em->flush(); } finally { $this->guard->deactivate(); }
 */
#[AsDoctrineListener(event: Events::preUpdate)]
class ReservationStatusWriteGuard
{
    private bool $active = false;

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Reservation) {
            return;
        }

        if (!$args->hasChangedField('reservationStatus')) {
            return;
        }

        if ($this->active) {
            return;
        }

        throw new \LogicException(sprintf(
            'Direct write to Reservation#%d.reservationStatus is forbidden. '
            . 'Use ReservationLifecycleManager::transition() instead.',
            $entity->getId() ?? 0,
        ));
    }
}
