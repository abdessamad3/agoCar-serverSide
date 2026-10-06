<?php

namespace App\Trait;

use App\Entity\Bureau;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Extracts the bureau ID from the authenticated user.
 * Checks user.bureau first, then falls back to Bureau.manager = user -- staff
 * and bureau-managers are ALWAYS locked to their own bureau this way, never
 * from a query param (so one staff account can never read another bureau's
 * data by just passing a different bureauId).
 * ROLE_ADMIN users with no bureau and not managing any bureau see all data
 * by default (returns null = no filter), but MAY voluntarily narrow that
 * down to one bureau via ?bureauId= (the frontend's bureau-switcher already
 * sends this on every list request) -- an admin-only, self-chosen filter,
 * never reachable for staff/managers above.
 * Any NON-admin who reaches this point (no bureau, not managing one) is a
 * misconfigured account, not an admin -- it must NOT fall through to the
 * same "null = unrestricted" result, or clearing a staff member's bureau
 * would silently grant them god-mode across every bureau and company. They
 * get 0 instead: a bureau ID that can never match a real row, so every
 * existing "= :bureauId" / "!== $bureauId" check across the app correctly
 * resolves to "sees nothing" without needing to special-case this value.
 */
trait BureauAwareTrait
{
    private EntityManagerInterface $bureauAwareEm;
    private RequestStack $bureauAwareRequestStack;

    #[Required]
    public function setBureauAwareEm(EntityManagerInterface $em): void
    {
        $this->bureauAwareEm = $em;
    }

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

        // Primary: user has bureau directly assigned
        if ($user->getBureau()) {
            return $user->getBureau()->getId();
        }

        // Fallback: user is manager of a bureau (bureau.manager = user)
        $bureau = $this->bureauAwareEm->getRepository(Bureau::class)->findOneBy(['manager' => $user]);
        if ($bureau) return $bureau->getId();

        // True admin (no bureau, not a bureau manager): honor an explicit,
        // self-chosen filter if one was sent, otherwise unrestricted (null).
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $raw = $this->bureauAwareRequestStack->getCurrentRequest()?->query->get('bureauId');
            if ($raw !== null && $raw !== '') {
                return (int) $raw;
            }
            return null;
        }

        // Non-admin, no bureau, not managing one: misconfigured account.
        // 0 never matches a real bureau, so this resolves to "sees nothing".
        return 0;
    }
}
