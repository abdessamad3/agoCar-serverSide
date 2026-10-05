<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Sums active (non-deleted) payment rows for a parent entity — the part of
 * "reconcile a parent's paid amount" that's genuinely identical between
 * DepensePaymentService::recalculate() (PaiementDepense -> Depense) and
 * PaiementController::recomputeReservationMontantPaye() (Paiement ->
 * Reservation). Each caller still owns its own write-back and any extra
 * status resolution (Depense also derives a StatusEnum; Reservation doesn't),
 * since that part is legitimately different per entity.
 */
final class PaymentSumHelper
{
    public static function sumActivePayments(
        EntityManagerInterface $em,
        string $paymentEntityClass,
        string $parentField,
        object $parent,
        string $amountField = 'montant',
    ): float {
        $sum = $em->createQueryBuilder()
            ->select("SUM(p.$amountField)")
            ->from($paymentEntityClass, 'p')
            ->where("p.$parentField = :parent")
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('parent', $parent)
            ->getQuery()
            ->getSingleScalarResult();

        return max(0.0, (float) ($sum ?? 0));
    }
}
