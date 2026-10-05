<?php

namespace App\Security\Voter;

use App\Entity\Utilisateur;
use App\Entity\Voiture;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls who can trigger lifecycle transitions on a vehicle.
 *
 * Attributes:
 *   VehicleLifecycleVoter::MANAGE      — create reservations, start rentals, add repairs/docs
 *   VehicleLifecycleVoter::ADMIN_ONLY  — decommission, sell, archive (terminal states)
 *
 * Usage in controllers:
 *   $this->denyAccessUnlessGranted(VehicleLifecycleVoter::MANAGE, $voiture);
 *   $this->denyAccessUnlessGranted(VehicleLifecycleVoter::ADMIN_ONLY, $voiture);
 */
class VehicleLifecycleVoter extends Voter
{
    public const MANAGE     = 'lifecycle.manage';
    public const ADMIN_ONLY = 'lifecycle.admin';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MANAGE, self::ADMIN_ONLY], true)
            && $subject instanceof Voiture;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Utilisateur) {
            return false;
        }

        $roles = $user->getRoles();

        return match ($attribute) {
            self::MANAGE     => in_array('ROLE_USER', $roles, true),
            self::ADMIN_ONLY => in_array('ROLE_ADMIN', $roles, true),
            default          => false,
        };
    }
}
