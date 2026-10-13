<?php

namespace App\Service;

use App\Entity\PaiementDepense;
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

    /**
     * Sums refund rows (negative PaiementDepense amounts) grouped by voiture.
     * A refund reduces the TRUE cost of an expense -- money actually came back --
     * but the Depense's own `montant` (the committed/invoiced total) is never
     * changed by a refund, only `montantPaye` is. Any "net profit" or "total
     * expenses" figure built from SUM(montant) alone therefore silently ignores
     * every refund, and any "unpaid balance" built from (montant - montantPaye)
     * falsely grows after a refund on an expense that was already fully settled.
     * Callers must subtract this from committed-cost sums, and ADD it back
     * before comparing paid-vs-committed for an "unpaid" figure (see
     * ProfitabilityService::getVehicleProfitability() for both usages).
     *
     * @return array<int, float> voitureId => refunded amount (positive)
     */
    public static function sumExpenseRefundsByVoiture(
        EntityManagerInterface $em,
        ?int $bureauId = null,
        ?int $carId = null,
        ?int $year = null,
    ): array {
        $qb = $em->createQueryBuilder()
            ->select('v.id as voitureId, SUM(pd.montant) as refunded')
            ->from(PaiementDepense::class, 'pd')
            ->join('pd.depense', 'd')
            ->join('d.voiture', 'v')
            ->where('pd.deletedAt IS NULL')
            ->andWhere('pd.montant < 0')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('v.deletedAt IS NULL')
            ->groupBy('v.id');
        if ($year !== null) $qb->andWhere('YEAR(pd.datePaiement) = :year')->setParameter('year', $year);
        if ($bureauId)      $qb->andWhere('v.bureau = :b')->setParameter('b', $bureauId);
        if ($carId)         $qb->andWhere('v.id = :cid')->setParameter('cid', $carId);

        $result = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $result[(int) $row['voitureId']] = abs((float) ($row['refunded'] ?? 0));
        }
        return $result;
    }
}
