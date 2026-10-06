<?php

namespace App\Trait;

use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Extracts the bureau ID from the authenticated user.
 * Single source of truth: user.bureau. (Previously also fell back to
 * Bureau.manager = user, but that meant two independent fields could grant
 * the same access and silently diverge -- e.g. clearing a manager's own
 * bureau didn't revoke their access, because they still matched the OTHER
 * path. Assigning someone as a bureau's manager now also sets their
 * user.bureau at write-time (see BureauController), so this stays a
 * one-step action for admins without the read side needing two checks.)
 * ROLE_ADMIN users with no bureau see all data by default (returns null =
 * no filter), but MAY voluntarily narrow that down to one bureau via
 * ?bureauId= (the frontend's bureau-switcher already sends this on every
 * list request) -- an admin-only, self-chosen filter, never reachable for
 * staff/managers above.
 * Any NON-admin with no bureau is a misconfigured account, not an admin --
 * it must NOT fall through to the same "null = unrestricted" result, or
 * clearing a staff member's bureau would silently grant them god-mode
 * across every bureau and company. They get 0 instead: a bureau ID that
 * can never match a real row, so every existing "= :bureauId" /
 * "!== $bureauId" check across the app correctly resolves to "sees
 * nothing" without needing to special-case this value.
 */
trait BureauAwareTrait
{
    private RequestStack $bureauAwareRequestStack;

    #[Required]
    public function setBureauAwareRequestStack(RequestStack $requestStack): void
    {
        $this->bureauAwareRequestStack = $requestStack;
    }

    protected function getEffectiveBureauId(): ?int
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) return null;

        if ($user->getBureau()) {
            return $user->getBureau()->getId();
        }

        // True admin (no bureau): honor an explicit, self-chosen filter if one
        // was sent, otherwise unrestricted (null).
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $raw = $this->bureauAwareRequestStack->getCurrentRequest()?->query->get('bureauId');
            if ($raw !== null && $raw !== '') {
                return (int) $raw;
            }
            return null;
        }

        // Non-admin, no bureau: misconfigured account.
        // 0 never matches a real bureau, so this resolves to "sees nothing".
        return 0;
    }
}
