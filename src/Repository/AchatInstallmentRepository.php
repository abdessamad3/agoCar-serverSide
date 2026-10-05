<?php

namespace App\Repository;

use App\Entity\AchatInstallment;
use App\Entity\AchatVoiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AchatInstallmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AchatInstallment::class);
    }

    /** Return all installments for an achat, ordered by number */
    public function findByAchat(AchatVoiture $achat): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.achatVoiture = :achat')
            ->setParameter('achat', $achat)
            ->orderBy('i.installmentNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Mark overdue: set status = overdue where dueDate < today and status = pending */
    public function markOverdue(): int
    {
        return $this->createQueryBuilder('i')
            ->update()
            ->set('i.status', ':overdue')
            ->where('i.dueDate < :today')
            ->andWhere('i.status = :pending')
            ->setParameter('overdue', 'overdue')
            ->setParameter('today', new \DateTimeImmutable())
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->execute();
    }

    /** Generate installment records for a newly created AchatVoiture (credit type) */
    public function generateForAchat(AchatVoiture $achat, \Doctrine\ORM\EntityManagerInterface $em): void
    {
        // Only generate if credit-based and schedule not already created
        if ($achat->getTypeFinancement() === 'comptant') return;
        $existing = $this->findByAchat($achat);
        if (!empty($existing)) return;

        $mensualite     = (float) ($achat->getMensualite() ?? 0);
        $dureeMois      = (int) ($achat->getDureeMois() ?? 0);
        $dateDebut      = $achat->getDateDebutCredit();
        $dernierMens    = (float) ($achat->getDernierMensualite() ?? $mensualite);

        if ($mensualite <= 0 || $dureeMois <= 0 || $dateDebut === null) return;

        for ($i = 1; $i <= $dureeMois; $i++) {
            $amount = ($i === $dureeMois && $dernierMens > 0) ? $dernierMens : $mensualite;
            $due    = $dateDebut->modify("+{$i} month");
            $status = $due < new \DateTimeImmutable() ? 'overdue' : 'pending';

            $inst = new AchatInstallment();
            $inst->setAchatVoiture($achat)
                 ->setInstallmentNumber($i)
                 ->setDueDate($due)
                 ->setAmount((string) $amount)
                 ->setStatus($status)
                 ->setCreeAu(new \DateTimeImmutable());
            $em->persist($inst);
        }
    }
}
