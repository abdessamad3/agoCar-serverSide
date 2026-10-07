<?php

namespace App\Service;

use App\Entity\Voiture;
use App\Repository\TarifSaisonnierRepository;

/**
 * Single authoritative source for a reservation's price. Nothing else should compute
 * or trust a client-submitted total — ReservationController::create()/update() and the
 * preview-total endpoint all go through this, so the number shown on screen and the
 * number actually saved can never drift apart (the exact bug class this replaces).
 */
class ReservationPricingService
{
    public function __construct(
        private readonly TarifSaisonnierRepository $tarifSaisonnierRepo,
    ) {}

    public function computeDays(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        return max(1, (int) ceil(($fin->getTimestamp() - $debut->getTimestamp()) / 86400));
    }

    /** Base vehicle rate, adjusted by whichever seasonal rule (if any) is active on the
     *  reservation's start date. The whole stay bills at this one rate — no proration
     *  for a stay that crosses into a different season partway through. */
    public function resolveDailyRate(Voiture $voiture, \DateTimeImmutable $dateDebut): string
    {
        $baseRate = (float) ($voiture->getPrixJour() ?? 0);
        $rule     = $this->tarifSaisonnierRepo->findActiveMatching($voiture, $dateDebut);
        $rate     = $rule ? $rule->applyTo($baseRate) : $baseRate;

        return number_format($rate, 2, '.', '');
    }

    /**
     * @param iterable $accessoires Accessoire entities (must respond to getPrix())
     * @return array{prixParJour: string, total: string}
     */
    public function computeTotal(
        Voiture $voiture,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        iterable $accessoires,
        ?string $remiseMontant,
    ): array {
        $days         = $this->computeDays($debut, $fin);
        $prixParJour  = $this->resolveDailyRate($voiture, $debut);
        $accessoryTotal = 0.0;
        foreach ($accessoires as $accessoire) {
            $accessoryTotal += ((float) $accessoire->getPrix()) * $days;
        }

        $total = ((float) $prixParJour * $days) + $accessoryTotal - (float) ($remiseMontant ?? 0);
        $total = max(0.0, $total);

        return [
            'prixParJour' => $prixParJour,
            'total'       => number_format($total, 2, '.', ''),
        ];
    }
}
