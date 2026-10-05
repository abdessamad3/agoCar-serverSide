<?php

namespace App\Repository\Trait;

use App\Entity\Voiture;

/**
 * Shared date-range overlap check for compliance-document repositories
 * (Assurance, SuiviTechnique) whose dates live on a joined Depense.
 * Previously implemented independently in both repositories — same query
 * shape, copy-pasted rather than shared.
 *
 * Not used by ReservationRepository::hasOverlap(), which has genuinely
 * different semantics (strict < / > so same-day handover isn't a conflict,
 * vs. the inclusive <= / >= used here where a one-day gap is required
 * between two compliance documents).
 */
trait DepenseOverlapTrait
{
    protected function hasDepenseOverlap(
        string $alias,
        Voiture $voiture,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        ?int $excludeId,
        array $extraActiveOnlyFields = [],
    ): bool {
        $qb = $this->createQueryBuilder($alias)
            ->join("$alias.depense", 'd')
            ->where('d.voiture = :voiture')
            ->andWhere("$alias.deletedAt IS NULL")
            ->andWhere('d.dateDebut <= :fin')
            ->andWhere('d.dateFin >= :debut')
            ->setParameter('voiture', $voiture)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);

        foreach ($extraActiveOnlyFields as $field) {
            $qb->andWhere("$alias.$field IS NULL");
        }

        if ($excludeId !== null) {
            $qb->andWhere("$alias.id != :excludeId")->setParameter('excludeId', $excludeId);
        }

        return count($qb->getQuery()->getResult()) > 0;
    }
}
