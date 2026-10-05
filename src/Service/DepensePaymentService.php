<?php

namespace App\Service;

use App\Entity\Depense;
use App\Entity\PaiementDepense;
use App\Enum\StatusEnum;
use App\Enum\PaymentTypeEnum;
use Doctrine\ORM\EntityManagerInterface;

class DepensePaymentService
{
    public function recalculate(int $depenseId, EntityManagerInterface $em): void
    {
        $depense = $em->find(Depense::class, $depenseId);
        if (!$depense) return;

        $totalPaid = PaymentSumHelper::sumActivePayments($em, PaiementDepense::class, 'depense', $depense);
        $total     = (float) ($depense->getMontant() ?? 0);

        $depense->setMontantPaye((string) $totalPaid);
        $depense->setStatut($this->resolveStatut($total, $totalPaid));
        $em->flush();
    }

    public function createInitialPayment(
        Depense $depense,
        float $amount,
        EntityManagerInterface $em,
        ?object $user = null
    ): void {
        if ($amount <= 0) return;

        $pd = new PaiementDepense();
        $pd->setDepense($depense);
        $pd->setMontant((string) $amount);
        $pd->setDatePaiement($depense->getCreeAu() ?? new \DateTimeImmutable());
        $pd->setPaymentType(PaymentTypeEnum::INITIAL);
        $pd->setPaymentReference($this->generateReference($em));
        $pd->setCreeAu(new \DateTimeImmutable());
        $pd->setCreePar($user);
        $em->persist($pd);
        $em->flush();

        $this->recalculate($depense->getId(), $em);
    }

    public function generateReference(EntityManagerInterface $em): string
    {
        $year  = date('Y');
        $count = (int) $em->getRepository(PaiementDepense::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
        return sprintf('PAY-%s-%06d', $year, $count + 1);
    }

    public static function paymentStatutLabel(float $total, float $paid): string
    {
        if ($total <= 0) return 'unpaid';
        if ($paid >= $total) return 'paid';
        if ($paid > 0) return 'partial';
        return 'unpaid';
    }

    public static function computeReste(float $total, float $paid): float
    {
        return max(0.0, $total - $paid);
    }

    private function resolveStatut(float $total, float $paid): StatusEnum
    {
        if ($total <= 0 || $paid <= 0) return StatusEnum::IMPAYE;
        if ($paid >= $total) return StatusEnum::PAYEE;
        return StatusEnum::PARTIEL;
    }
}
