<?php

namespace App\Command;

use App\Entity\Depense;
use App\Entity\PaiementDepense;
use App\Entity\SuiviTechnique;
use App\Entity\Vignette;
use App\Entity\Adblue;
use App\Entity\Assurance;
use App\Entity\Reparation;
use App\Entity\Vidange;
use App\Enum\StatusEnum;
use App\Enum\PaymentTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-expense-payments',
    description: 'Phase 1 refactor: unify SuiviTechnique, replace polymorphic FK with real FK, convert montantPayeInitial to INITIAL payment records'
)]
class MigrateExpensePaymentsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();

        // ── Step 1: Create Depense records for every SuiviTechnique ──────────
        $io->section('Step 1 — Creating Depense records for SuiviTechnique');

        $suivis = $this->em->createQueryBuilder()
            ->select('s')
            ->from(SuiviTechnique::class, 's')
            ->getQuery()->getResult();

        foreach ($suivis as $suivi) {
            if ($suivi->getDepense() !== null) {
                $io->note("SuiviTechnique #{$suivi->getId()} already has a Depense — skipped.");
                continue;
            }

            $depense = new Depense();
            $depense->setDate($suivi->getDateReglages() ?? new \DateTimeImmutable());
            $depense->setTypeDepense('suivi_technique');
            $depense->setMontant($suivi->getMontantTotal() ?? '0');
            $depense->setMontantPaye($suivi->getMontantPaye() ?? '0');
            $depense->setStatut($suivi->getStatut() ?? StatusEnum::IMPAYE);
            $depense->setVoiture($suivi->getVoiture());
            if ($suivi->getVoiture()?->getBureau()) {
                $depense->setBureau($suivi->getVoiture()->getBureau());
            }
            $depense->setCreePar($suivi->getCreePar());
            $depense->setCreeAu($suivi->getCreeAu() ?? new \DateTimeImmutable());
            $this->em->persist($depense);
            $this->em->flush();

            $suivi->setDepense($depense);
            $this->em->flush();

            $io->text("  SuiviTechnique #{$suivi->getId()} → Depense #{$depense->getId()} created.");
        }

        // ── Step 2: Populate linked_depense_id on paiement_depense ───────────
        $io->section('Step 2 — Migrating PaiementDepense to real FK (linked_depense_id)');

        $paiements = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PaiementDepense::class, 'p')
            ->getQuery()->getResult();

        $migrated = 0;
        $skipped  = 0;
        foreach ($paiements as $pd) {
            if ($pd->getDepense() !== null) { $skipped++; continue; }

            $type     = $pd->getDepenseType();
            $entityId = $pd->getDepenseId();

            $depense = match ($type) {
                'vignette'       => $this->em->find(Vignette::class, $entityId)?->getDepense(),
                'adblue'         => $this->em->find(Adblue::class, $entityId)?->getDepense(),
                'assurance'      => $this->em->find(Assurance::class, $entityId)?->getDepense(),
                'reparation'     => $this->em->find(Reparation::class, $entityId)?->getDepense(),
                'vidange'        => $this->em->find(Vidange::class, $entityId)?->getDepense(),
                'suivitechnique' => $this->em->find(SuiviTechnique::class, $entityId)?->getDepense(),
                default          => null,
            };

            if ($depense) {
                $pd->setDepense($depense);
                $pd->setPaymentType(PaymentTypeEnum::INSTALLMENT);
                $migrated++;
            } else {
                $io->warning("  PaiementDepense #{$pd->getId()} (type={$type}, entityId={$entityId}) — entity not found, skipped.");
                $skipped++;
            }
        }
        $this->em->flush();
        $io->text("  Migrated: {$migrated} | Already done / not found: {$skipped}");

        // ── Step 3: Convert montantPayeInitial → INITIAL payment records ──────
        $io->section('Step 3 — Converting montantPayeInitial into INITIAL PaiementDepense records');

        $depenses = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Depense::class, 'd')
            ->where('d.montantPayeInitial IS NOT NULL')
            ->andWhere('d.montantPayeInitial > 0')
            ->getQuery()->getResult();

        $created = 0;
        foreach ($depenses as $depense) {
            $existing = $this->em->getRepository(PaiementDepense::class)->findOneBy([
                'depense'     => $depense,
                'paymentType' => PaymentTypeEnum::INITIAL,
                'deletedAt'   => null,
            ]);
            if ($existing) continue;

            $pd = new PaiementDepense();
            $pd->setDepense($depense);
            $pd->setMontant($depense->getMontantPayeInitial());
            $pd->setDatePaiement($depense->getCreeAu() ?? new \DateTimeImmutable());
            $pd->setPaymentType(PaymentTypeEnum::INITIAL);
            $pd->setNote('Paiement initial (migré automatiquement)');
            $pd->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($pd);
            $created++;
        }
        $this->em->flush();
        $io->text("  Created {$created} INITIAL payment records.");

        // ── Step 4: Also create INITIAL records for SuiviTechnique montantPayeInitial ──
        $io->section('Step 4 — Converting SuiviTechnique montantPayeInitial');

        $suivis = $this->em->createQueryBuilder()
            ->select('s')
            ->from(SuiviTechnique::class, 's')
            ->where('s.montantPayeInitial IS NOT NULL')
            ->andWhere('s.montantPayeInitial > 0')
            ->getQuery()->getResult();

        $created = 0;
        foreach ($suivis as $suivi) {
            $depense = $suivi->getDepense();
            if (!$depense) continue;

            $existing = $this->em->getRepository(PaiementDepense::class)->findOneBy([
                'depense'     => $depense,
                'paymentType' => PaymentTypeEnum::INITIAL,
                'deletedAt'   => null,
            ]);
            if ($existing) continue;

            $pd = new PaiementDepense();
            $pd->setDepense($depense);
            $pd->setMontant($suivi->getMontantPayeInitial());
            $pd->setDatePaiement($suivi->getCreeAu() ?? new \DateTimeImmutable());
            $pd->setPaymentType(PaymentTypeEnum::INITIAL);
            $pd->setNote('Paiement initial suivi technique (migré)');
            $pd->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($pd);
            $created++;
        }
        $this->em->flush();
        $io->text("  Created {$created} INITIAL records for SuiviTechnique.");

        // ── Step 5: DB column cleanup ─────────────────────────────────────────
        $io->section('Step 5 — DB column cleanup');

        // Rename linked_depense_id → depense_id and make it NOT NULL
        // Drop legacy polymorphic columns from paiement_depense
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        // Drop old constraints that reference the legacy depense_id if any
        try {
            $conn->executeStatement('ALTER TABLE paiement_depense DROP FOREIGN KEY FK_paiement_depense_linked');
        } catch (\Exception $e) { /* ignore if not exists */ }

        $conn->executeStatement('ALTER TABLE paiement_depense DROP COLUMN depense_type');
        $conn->executeStatement('ALTER TABLE paiement_depense DROP COLUMN depense_id');
        $conn->executeStatement('ALTER TABLE paiement_depense CHANGE linked_depense_id depense_id INT DEFAULT NULL');

        // Drop legacy standalone payment fields from suivi_technique
        $conn->executeStatement('ALTER TABLE suivi_technique DROP COLUMN montant_total');
        $conn->executeStatement('ALTER TABLE suivi_technique DROP COLUMN montant_paye');
        $conn->executeStatement('ALTER TABLE suivi_technique DROP COLUMN montant_paye_initial');
        $conn->executeStatement('ALTER TABLE suivi_technique DROP COLUMN statut');

        // Drop montantPayeInitial from depense
        $conn->executeStatement('ALTER TABLE depense DROP COLUMN montant_paye_initial');

        // Drop mirrored montantPaye from reparation (tracked exclusively via Depense)
        $conn->executeStatement('ALTER TABLE reparation DROP COLUMN montant_paye');

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $io->text('  Legacy columns dropped. linked_depense_id renamed to depense_id.');
        $io->success('Migration completed. Run doctrine:schema:update --force to finalise.');

        return Command::SUCCESS;
    }
}
