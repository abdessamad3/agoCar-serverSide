<?php

namespace App\Doctrine;

use App\Entity\Voiture;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

/**
 * Prevents any code from writing Voiture.voitureStatus directly.
 * Only FleetLifecycleManager::applyEvent() may change this field.
 *
 * Usage in FleetLifecycleManager:
 *   $this->guard->activate();
 *   try { $this->em->flush(); } finally { $this->guard->deactivate(); }
 */
#[AsDoctrineListener(event: Events::preUpdate)]
class VoitureStatusWriteGuard
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

        if (!$entity instanceof Voiture) {
            return;
        }

        if (!$args->hasChangedField('voitureStatus')) {
            return;
        }

        if ($this->active) {
            return;
        }

        throw new \LogicException(sprintf(
            'Direct write to Voiture#%d.voitureStatus is forbidden. '
            . 'Use FleetLifecycleManager::applyEvent() instead.',
            $entity->getId() ?? 0,
        ));
    }
}
