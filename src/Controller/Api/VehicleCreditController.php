<?php

namespace App\Controller\Api;

use App\Entity\VehicleCredit;
use App\Entity\VehicleCreditInstallment;
use App\Entity\VehicleCreditReminder;
use App\Repository\VehicleCreditRepository;
use App\Repository\VoitureRepository;
use App\Repository\FournisseurRepository;
use App\Repository\FinancialInstitutionRepository;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vehicle-credit', name: 'app_api_vehicle_credit_')]
#[IsGranted('ROLE_USER')]
class VehicleCreditController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    /** Bureau-locked staff/managers may only touch vehicle credits belonging to their
     *  own bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(VehicleCredit $vc): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($vc->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Crédit introuvable');
        }
    }

    // ── Serializer ────────────────────────────────────────────────────────────

    private function serialize(VehicleCredit $vc, bool $withInstallments = false, bool $withPayments = false): array
    {
        $v  = $vc->getVoiture();
        $fi = $vc->getFinancialInstitution();
        $s  = $vc->getSupplier();

        $totalPaid = array_reduce(
            $vc->getPayments()->toArray(),
            fn($carry, $p) => $carry + (float) $p->getAmount(),
            0.0
        );

        $data = [
            'id'                    => $vc->getId(),
            'voitureId'             => $v?->getId(),
            'voiture'               => $v ? trim(($v->getMarque() ?? '') . ' ' . ($v->getModele() ?? '')) : null,
            'immatriculation'       => $v?->getImmatriculation(),
            'supplierId'            => $s?->getId(),
            'supplier'              => $s ? ($s->getRaisonSociale() ?? $s->getNom() ?? '') : null,
            'financialInstitutionId'=> $fi?->getId(),
            'financialInstitution'  => $fi?->getName(),
            'institutionType'       => $fi?->getType(),
            'contractNumber'        => $vc->getContractNumber(),
            'vehiclePrice'          => $vc->getVehiclePrice(),
            'downPayment'           => $vc->getDownPayment(),
            'financedAmount'        => $vc->getFinancedAmount(),
            'interestRate'          => $vc->getInterestRate(),
            'durationMonths'        => $vc->getDurationMonths(),
            'monthlyInstallment'    => $vc->getMonthlyInstallment(),
            'totalCost'             => $vc->getTotalCost(),
            'startDate'             => $vc->getStartDate()?->format('Y-m-d'),
            'endDate'               => $vc->getEndDate()?->format('Y-m-d'),
            'firstPaymentDate'      => $vc->getFirstPaymentDate()?->format('Y-m-d'),
            'dueDay'                => $vc->getDueDay(),
            'remainingBalance'      => $vc->getRemainingBalance(),
            'totalPaid'             => round($totalPaid, 2),
            'progressPct'           => $vc->getTotalCost() > 0
                ? round(($totalPaid / (float)$vc->getTotalCost()) * 100, 1)
                : 0,
            'status'                => $vc->getStatus(),
            'notes'                 => $vc->getNotes(),
            'purchaseInvoiceNumber' => $vc->getPurchaseInvoiceNumber(),
            'purchaseDate'          => $vc->getPurchaseDate()?->format('Y-m-d'),
            'installmentCount'      => $vc->getInstallments()->count(),
            'paidInstallments'      => $vc->getInstallments()->filter(fn($i) => $i->getStatus() === 'paid')->count(),
            'overdueInstallments'   => $vc->getInstallments()->filter(fn($i) => $i->getStatus() === 'overdue')->count(),
            'createdAt'             => $vc->getCreatedAt()?->format('Y-m-d'),
        ];

        if ($withInstallments) {
            $data['installments'] = $vc->getInstallments()->map(
                fn($i) => $this->serializeInstallment($i)
            )->toArray();
        }

        if ($withPayments) {
            $data['payments'] = $vc->getPayments()->map(fn($p) => [
                'id'              => $p->getId(),
                'amount'          => $p->getAmount(),
                'paymentDate'     => $p->getPaymentDate()?->format('Y-m-d'),
                'paymentType'     => $p->getPaymentType(),
                'paymentMethod'   => $p->getPaymentMethod(),
                'referenceNumber' => $p->getReferenceNumber(),
                'notes'           => $p->getNotes(),
                'attachments'     => $p->getAttachments()->map(fn($a) => [
                    'id'       => $a->getId(),
                    'fileName' => $a->getFileName(),
                    'filePath' => $a->getFilePath(),
                    'fileType' => $a->getFileType(),
                ])->toArray(),
            ])->toArray();
        }

        return $data;
    }

    private function serializeInstallment(VehicleCreditInstallment $i): array
    {
        $today = new \DateTimeImmutable('today');
        $due   = $i->getDueDate();

        $daysUntilDue = $due
            ? (int) $today->diff(\DateTimeImmutable::createFromInterface($due))->format('%r%a')
            : null;

        $latestPayment = $i->getPayments()->isEmpty() ? null : $i->getPayments()->last();

        return [
            'id'                => $i->getId(),
            'installmentNumber' => $i->getInstallmentNumber(),
            'dueDate'           => $due?->format('Y-m-d'),
            'principalAmount'   => $i->getPrincipalAmount(),
            'interestAmount'    => $i->getInterestAmount(),
            'amountDue'         => $i->getAmountDue(),
            'amountPaid'        => $i->getAmountPaid(),
            'remainingAmount'   => $i->getRemainingAmount(),
            'paidAt'            => $i->getPaidAt()?->format('Y-m-d'),
            'status'            => $i->getStatus(),
            'daysUntilDue'      => $daysUntilDue,
            'paymentId'         => $latestPayment?->getId(),
        ];
    }

    // ── List ──────────────────────────────────────────────────────────────────

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $status   = $request->query->get('status');
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('vc')
            ->from(VehicleCredit::class, 'vc')
            ->join('vc.voiture', 'v')
            ->orderBy('vc.createdAt', 'DESC');

        if ($bureauId !== null) {
            $qb->where('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }
        if ($status) {
            $qb->andWhere('vc.status = :status')->setParameter('status', $status);
        }

        $voitureId = (int) $request->query->get('voitureId', 0);
        if ($voitureId) {
            $qb->andWhere('vc.voiture = :voitureId')->setParameter('voitureId', $voitureId);
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);

        foreach ($items as $vc) {
            $this->refreshInstallmentStatuses($vc, $em);
        }
        $em->flush();

        return $this->json(['data' => array_map(fn($vc) => $this->serialize($vc), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(VehicleCredit $vc, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vc);
        $this->refreshInstallmentStatuses($vc, $em);
        $em->flush();
        return $this->json($this->serialize($vc, true, true));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        FournisseurRepository $fournisseurRepo,
        FinancialInstitutionRepository $fiRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $vc = new VehicleCredit();

        if (!empty($data['voitureId'])) {
            $v = $voitureRepo->find($data['voitureId']);
            if (!$v) return $this->json(['error' => 'Véhicule introuvable'], 404);
            $vc->setVoiture($v);
        }
        if (!empty($data['supplierId'])) {
            $vc->setSupplier($fournisseurRepo->find($data['supplierId']));
        }
        if (!empty($data['financialInstitutionId'])) {
            $vc->setFinancialInstitution($fiRepo->find($data['financialInstitutionId']));
        }

        $vehiclePrice  = (float) ($data['vehiclePrice'] ?? 0);
        $downPayment   = (float) ($data['downPayment'] ?? 0);
        $financedAmount = $vehiclePrice - $downPayment;
        $interestRate  = (float) ($data['interestRate'] ?? 0);
        $durationMonths = (int) ($data['durationMonths'] ?? 12);

        // Monthly installment: French amortization formula
        // M = P * (r(1+r)^n) / ((1+r)^n - 1)  where r = monthly rate
        $monthlyInstallment = $this->calculateMonthlyInstallment($financedAmount, $interestRate, $durationMonths);
        $totalCost = round($monthlyInstallment * $durationMonths, 2);

        $startDate = new \DateTimeImmutable($data['startDate']);
        $endDate   = (clone $startDate)->modify("+{$durationMonths} months");

        $dueDay = (int) ($data['dueDay'] ?? (int) $startDate->format('d'));
        $firstPaymentDate = new \DateTimeImmutable(
            $data['firstPaymentDate']
            ?? $startDate->format('Y-m') . '-' . str_pad((string)$dueDay, 2, '0', STR_PAD_LEFT)
        );

        $vc->setContractNumber($data['contractNumber'] ?? null);
        $vc->setVehiclePrice((string) $vehiclePrice);
        $vc->setDownPayment((string) $downPayment);
        $vc->setFinancedAmount((string) $financedAmount);
        $vc->setInterestRate((string) $interestRate);
        $vc->setDurationMonths($durationMonths);
        $vc->setMonthlyInstallment((string) $monthlyInstallment);
        $vc->setTotalCost((string) $totalCost);
        $vc->setStartDate($startDate);
        $vc->setEndDate($endDate);
        $vc->setFirstPaymentDate($firstPaymentDate);
        $vc->setDueDay($dueDay);
        $vc->setRemainingBalance((string) $totalCost);
        $vc->setStatus($data['status'] ?? 'draft');
        $vc->setNotes($data['notes'] ?? null);
        $vc->setPurchaseInvoiceNumber($data['purchaseInvoiceNumber'] ?? null);
        if (!empty($data['purchaseDate'])) {
            $vc->setPurchaseDate(new \DateTimeImmutable($data['purchaseDate']));
        }

        $em->persist($vc);

        // Auto-generate installment schedule when activating
        if (in_array($vc->getStatus(), ['active', 'pending_approval'])) {
            $this->generateInstallments($vc, $em);
        }

        $em->flush();

        return $this->json(['message' => 'Contrat créé', 'id' => $vc->getId()], 201);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        VehicleCredit $vc,
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        FournisseurRepository $fournisseurRepo,
        FinancialInstitutionRepository $fiRepo
    ): JsonResponse {
        $this->assertBureauAccess($vc);
        $data = json_decode($request->getContent(), true);
        $prevStatus = $vc->getStatus();

        if (!empty($data['voitureId']))              $vc->setVoiture($voitureRepo->find($data['voitureId']));
        if (isset($data['supplierId']))              $vc->setSupplier($fournisseurRepo->find($data['supplierId']));
        if (isset($data['financialInstitutionId']))  $vc->setFinancialInstitution($fiRepo->find($data['financialInstitutionId']));
        if (isset($data['contractNumber']))          $vc->setContractNumber($data['contractNumber']);
        if (isset($data['notes']))                   $vc->setNotes($data['notes']);
        if (isset($data['purchaseInvoiceNumber']))   $vc->setPurchaseInvoiceNumber($data['purchaseInvoiceNumber']);
        if (isset($data['status']))                  $vc->setStatus($data['status']);
        if (isset($data['purchaseDate']))            $vc->setPurchaseDate(new \DateTimeImmutable($data['purchaseDate']));

        $vc->setUpdatedAt(new \DateTimeImmutable());

        // Generate installments on first activation (only if none exist yet)
        if ($prevStatus !== 'active' && $vc->getStatus() === 'active' && $vc->getInstallments()->isEmpty()) {
            $this->generateInstallments($vc, $em);
        }

        $em->flush();

        return $this->json(['message' => 'Contrat mis à jour']);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(VehicleCredit $vc, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vc);
        $vc->setStatus('cancelled');
        $vc->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();
        return $this->json(['message' => 'Contrat annulé']);
    }

    // ── Installments endpoint ────────────────────────────────────────────────

    #[Route('/{id}/installments', name: 'installments', methods: ['GET'])]
    public function installments(VehicleCredit $vc, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vc);
        $this->refreshInstallmentStatuses($vc, $em);
        $em->flush();
        $items = $vc->getInstallments()->toArray();
        return $this->json(array_map(fn($i) => $this->serializeInstallment($i), $items));
    }

    // ── Dashboard stats ───────────────────────────────────────────────────────

    #[Route('/stats/dashboard', name: 'dashboard_stats', methods: ['GET'])]
    public function dashboardStats(VehicleCreditRepository $repo, EntityManagerInterface $em, Request $request): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();

        $qb = $em->createQueryBuilder()->select('vc')->from(VehicleCredit::class, 'vc')->join('vc.voiture', 'v');
        if ($bureauId !== null) {
            $qb->where('v.bureau = :b')->setParameter('b', $bureauId);
        }
        $all = $qb->getQuery()->getResult();

        $today   = new \DateTimeImmutable('today');
        $thisMonthStart = new \DateTimeImmutable('first day of this month');
        $thisMonthEnd   = new \DateTimeImmutable('last day of this month');

        $totalFinanced     = 0.0;
        $totalRemaining    = 0.0;
        $totalPaid         = 0.0;
        $activeCount       = 0;
        $completedCount    = 0;
        $overdueCount      = 0;
        $dueSoonCount      = 0;
        $dueTodayCount     = 0;
        $amountDueThisMonth = 0.0;
        $overdueAmount     = 0.0;

        foreach ($all as $vc) {
            $totalFinanced  += (float) $vc->getFinancedAmount();
            $totalRemaining += (float) $vc->getRemainingBalance();

            $paid = array_reduce(
                $vc->getPayments()->toArray(),
                fn($c, $p) => $c + (float) $p->getAmount(),
                0.0
            );
            $totalPaid += $paid;

            match ($vc->getStatus()) {
                'active'    => $activeCount++,
                'completed' => $completedCount++,
                'defaulted' => $overdueCount++,
                default     => null,
            };

            foreach ($vc->getInstallments() as $inst) {
                $due = $inst->getDueDate();
                if (!$due || $inst->getStatus() === 'paid') continue;

                $remaining = (float) $inst->getRemainingAmount();

                // Overdue
                if ($due < $today) {
                    $overdueCount++;
                    $overdueAmount += $remaining;
                }

                // Due today
                if ($due->format('Y-m-d') === $today->format('Y-m-d')) {
                    $dueTodayCount++;
                }

                // Due soon (within 7 days)
                $daysUntil = (int) $today->diff(\DateTimeImmutable::createFromInterface($due))->format('%r%a');
                if ($daysUntil > 0 && $daysUntil <= 7) {
                    $dueSoonCount++;
                }

                // This month
                if ($due >= $thisMonthStart && $due <= $thisMonthEnd) {
                    $amountDueThisMonth += $remaining;
                }
            }
        }

        return $this->json([
            'totalFinancedVehicles' => count($all),
            'totalFinancedAmount'   => round($totalFinanced, 2),
            'totalRemainingDebt'    => round($totalRemaining, 2),
            'totalAmountPaid'       => round($totalPaid, 2),
            'activeContracts'       => $activeCount,
            'completedContracts'    => $completedCount,
            'overdueContracts'      => $overdueCount,
            'dueSoonInstallments'   => $dueSoonCount,
            'dueTodayInstallments'  => $dueTodayCount,
            'amountDueThisMonth'    => round($amountDueThisMonth, 2),
            'overdueAmount'         => round($overdueAmount, 2),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function calculateMonthlyInstallment(float $principal, float $annualRate, int $months): float
    {
        if ($annualRate <= 0) {
            return $months > 0 ? round($principal / $months, 2) : 0;
        }
        $r = ($annualRate / 100) / 12; // monthly rate
        $installment = $principal * ($r * pow(1 + $r, $months)) / (pow(1 + $r, $months) - 1);
        return round($installment, 2);
    }

    private function generateInstallments(VehicleCredit $vc, EntityManagerInterface $em): void
    {
        $principal    = (float) $vc->getFinancedAmount();
        $annualRate   = (float) $vc->getInterestRate();
        $months       = $vc->getDurationMonths();
        $monthly      = (float) $vc->getMonthlyInstallment();
        $firstPayDate = $vc->getFirstPaymentDate() ?? $vc->getStartDate();
        $dueDay       = $vc->getDueDay() ?? (int) $firstPayDate->format('d');

        $r             = $annualRate > 0 ? ($annualRate / 100) / 12 : 0;
        $balance       = $principal;
        $currentDate   = \DateTimeImmutable::createFromInterface($firstPayDate);

        for ($n = 1; $n <= $months; $n++) {
            $interest  = $r > 0 ? round($balance * $r, 2) : 0.0;
            $principal_part = round($monthly - $interest, 2);
            // Last installment: adjust for rounding
            if ($n === $months) {
                $principal_part = round($balance, 2);
                $monthly        = round($principal_part + $interest, 2);
            }
            $balance = max(0, round($balance - $principal_part, 2));

            // Build due date: set day to dueDay in the correct month
            $dueDate = $this->buildDueDate($currentDate, $dueDay);

            $inst = new VehicleCreditInstallment();
            $inst->setVehicleCredit($vc);
            $inst->setInstallmentNumber($n);
            $inst->setDueDate($dueDate);
            $inst->setPrincipalAmount((string) $principal_part);
            $inst->setInterestAmount((string) $interest);
            $inst->setAmountDue((string) $monthly);
            $inst->setAmountPaid('0');
            $inst->setRemainingAmount((string) $monthly);
            $inst->setStatus('pending');

            $em->persist($inst);

            // Generate reminders for this installment
            $this->generateRemindersForInstallment($inst, $vc, $em);

            $currentDate = $currentDate->modify('+1 month');
        }
    }

    private function buildDueDate(\DateTimeImmutable $base, int $dueDay): \DateTimeImmutable
    {
        $maxDay = (int) $base->format('t'); // days in month
        $day    = min($dueDay, $maxDay);
        return new \DateTimeImmutable($base->format('Y-m') . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT));
    }

    private function generateRemindersForInstallment(
        VehicleCreditInstallment $inst,
        VehicleCredit $vc,
        EntityManagerInterface $em
    ): void {
        $due = \DateTimeImmutable::createFromInterface($inst->getDueDate());

        $reminderDefs = [
            ['days' => -15, 'type' => '15_days'],
            ['days' => -7,  'type' => '7_days'],
            ['days' => -3,  'type' => '3_days'],
            ['days' => 0,   'type' => 'due_today'],
        ];

        foreach ($reminderDefs as $def) {
            $reminderDate = $due->modify("{$def['days']} days");
            if ($reminderDate < new \DateTimeImmutable('today')) continue;

            $reminder = new VehicleCreditReminder();
            $reminder->setVehicleCredit($vc);
            $reminder->setInstallment($inst);
            $reminder->setReminderDate($reminderDate);
            $reminder->setReminderType($def['type']);
            $reminder->setStatus('pending');

            $em->persist($reminder);
        }
    }

    private function refreshInstallmentStatuses(VehicleCredit $vc, EntityManagerInterface $em): void
    {
        if ($vc->getStatus() === 'completed' || $vc->getStatus() === 'cancelled') return;

        $today = new \DateTimeImmutable('today');

        foreach ($vc->getInstallments() as $inst) {
            if ($inst->getStatus() === 'paid') continue;

            $due      = \DateTimeImmutable::createFromInterface($inst->getDueDate());
            $days     = (int) $today->diff($due)->format('%r%a');
            $remaining = (float) $inst->getRemainingAmount();

            if ($remaining <= 0) {
                $inst->setStatus('paid');
                if (!$inst->getPaidAt()) $inst->setPaidAt($today);
            } elseif ($days < 0) {
                $inst->setStatus('overdue');
            } elseif ($days === 0) {
                $inst->setStatus('due_today');
            } elseif ($days <= 7) {
                $inst->setStatus('due_soon');
            } elseif ((float) $inst->getAmountPaid() > 0) {
                $inst->setStatus('partial');
            } else {
                $inst->setStatus('pending');
            }
        }

        // Auto-complete contract when remaining balance <= 0
        if ((float) $vc->getRemainingBalance() <= 0 && $vc->getStatus() === 'active') {
            $vc->setStatus('completed');
            $vc->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
