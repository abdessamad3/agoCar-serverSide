<?php

namespace App\Controller\Api;

use App\Entity\AchatVoiture;
use App\Entity\VehicleCredit;
use App\Entity\VehicleCreditInstallment;
use App\Entity\VehicleCreditReminder;
use App\Repository\AchatVoitureRepository;
use App\Repository\VoitureRepository;
use App\Repository\FournisseurRepository;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/achat-voiture', name: 'app_api_achat_voiture_')]
class AchatVoitureController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    private function serialize(AchatVoiture $a): array
    {
        $v = $a->getVoiture();
        $f = $a->getFournisseur();
        return [
            'id'                => $a->getId(),
            'voitureId'         => $v?->getId(),
            'voiture'           => trim(($v?->getMarque() ?? '') . ' ' . ($v?->getModele() ?? '')),
            'fournisseurId'     => $f?->getId(),
            'fournisseur'       => $f ? ($f->getRaisonSociale() ?? $f->getNom() ?? '') : null,
            'dateAchat'         => $a->getDateAchat()?->format('Y-m-d'),
            'prixAchat'         => $a->getPrixAchat(),
            'apport'            => $a->getApport(),
            'typeFinancement'   => $a->getTypeFinancement(),
            'mensualite'        => $a->getMensualite(),
            'tauxInteret'       => $a->getTauxInteret(),
            'dateDebutCredit'   => $a->getDateDebutCredit()?->format('Y-m-d'),
            'resteAFinancer'    => $a->getResteAFinancer(),
            'dureeMois'         => $a->getDureeMois(),
            'dernierMensualite' => $a->getDernierMensualite(),
            'statut'            => $a->getStatut(),
            'notes'             => $a->getNotes(),
            'creeAu'            => $a->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\AchatVoiture::class, 'a')
            ->join('a.voiture', 'v')
            ->orderBy('a.creeAu', 'DESC');

        if ($bureauId) {
            $qb->where('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        $voitureId = (int) $request->query->get('voitureId', 0);
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map(fn($a) => $this->serialize($a), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(AchatVoiture $achatVoiture): JsonResponse
    {
        return $this->json($this->serialize($achatVoiture));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        FournisseurRepository $fournisseurRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $a = new AchatVoiture();

        if (!empty($data['voitureId'])) {
            $v = $voitureRepo->find($data['voitureId']);
            if ($v) $a->setVoiture($v);
        }
        if (!empty($data['fournisseurId'])) {
            $f = $fournisseurRepo->find($data['fournisseurId']);
            if ($f) $a->setFournisseur($f);
        }

        $a->setDateAchat(new \DateTimeImmutable($data['dateAchat'] ?? 'now'));
        $a->setPrixAchat($data['prixAchat'] ?? 0);
        $a->setApport($data['apport'] ?? null);
        $a->setTypeFinancement($data['typeFinancement'] ?? 'comptant');
        $a->setMensualite($data['mensualite'] ?? null);
        $a->setDureeMois(isset($data['dureeMois']) ? (int) $data['dureeMois'] : null);

        $monthly   = (float) ($data['mensualite'] ?? 0);
        $months    = isset($data['dureeMois']) ? (int) $data['dureeMois'] : 0;
        $principal = (float) ($data['prixAchat'] ?? 0) - (float) ($data['apport'] ?? 0);
        $rate      = ($monthly > 0 && $months > 0 && $principal > 0)
            ? $this->calcAnnualRate($principal, $monthly, $months)
            : null;
        $a->setTauxInteret($rate !== null ? (string) $rate : null);
        $a->setResteAFinancer($principal > 0 ? (string) $principal : null);
        $a->setDernierMensualite($data['dernierMensualite'] ?? null);
        $a->setStatut($data['statut'] ?? 'actif');
        $a->setNotes($data['notes'] ?? null);
        $a->setCreeAu(new \DateTimeImmutable());

        if (!empty($data['dateDebutCredit'])) {
            $a->setDateDebutCredit(new \DateTimeImmutable($data['dateDebutCredit']));
        }

        $em->persist($a);
        $em->flush();

        if ($a->getTypeFinancement() === 'credit') {
            $this->createVehicleCreditForAchat($a, $data, $em);
            $em->flush();
        }

        return $this->json(['message' => 'Achat créé', 'id' => $a->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        AchatVoiture $achatVoiture,
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        FournisseurRepository $fournisseurRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['voitureId'])) {
            $v = $voitureRepo->find($data['voitureId']);
            $achatVoiture->setVoiture($v ?: null);
        }
        if (array_key_exists('fournisseurId', $data)) {
            $f = $data['fournisseurId'] ? $fournisseurRepo->find($data['fournisseurId']) : null;
            $achatVoiture->setFournisseur($f);
        }
        if (isset($data['dateAchat']))         $achatVoiture->setDateAchat(new \DateTimeImmutable($data['dateAchat']));
        if (isset($data['prixAchat']))         $achatVoiture->setPrixAchat($data['prixAchat']);
        if (array_key_exists('apport', $data)) $achatVoiture->setApport($data['apport']);
        if (isset($data['typeFinancement']))   $achatVoiture->setTypeFinancement($data['typeFinancement']);
        if (array_key_exists('mensualite', $data))        $achatVoiture->setMensualite($data['mensualite']);
        if (array_key_exists('tauxInteret', $data))       $achatVoiture->setTauxInteret($data['tauxInteret']);
        if (array_key_exists('resteAFinancer', $data))    $achatVoiture->setResteAFinancer($data['resteAFinancer']);
        if (array_key_exists('dureeMois', $data))         $achatVoiture->setDureeMois($data['dureeMois']);
        if (array_key_exists('dernierMensualite', $data)) $achatVoiture->setDernierMensualite($data['dernierMensualite']);
        if (isset($data['statut']))            $achatVoiture->setStatut($data['statut']);
        if (array_key_exists('notes', $data))  $achatVoiture->setNotes($data['notes']);
        if (array_key_exists('dateDebutCredit', $data)) {
            $achatVoiture->setDateDebutCredit($data['dateDebutCredit'] ? new \DateTimeImmutable($data['dateDebutCredit']) : null);
        }
        $achatVoiture->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Achat mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(AchatVoiture $achatVoiture, EntityManagerInterface $em): JsonResponse
    {
        $achatVoiture->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Achat supprimé'], 200);
    }

    // ── Credit auto-creation ──────────────────────────────────────────────────

    private function createVehicleCreditForAchat(AchatVoiture $a, array $data, EntityManagerInterface $em): void
    {
        $vehiclePrice   = (float) $a->getPrixAchat();
        $downPayment    = (float) ($a->getApport() ?? 0);
        $financedAmount = $vehiclePrice - $downPayment;
        $monthly        = (float) ($a->getMensualite() ?? 0);
        $interestRate   = (float) ($a->getTauxInteret() ?? 0);

        if ($financedAmount <= 0 || $monthly <= 0) return;

        $durationMonths = $interestRate > 0
            ? (int) ($a->getDureeMois() ?? (int) round($financedAmount / $monthly))
            : (int) floor($financedAmount / $monthly);

        if ($durationMonths <= 0) return;

        $monthlyInstallment = $interestRate > 0
            ? $this->calcMonthlyInstallment($financedAmount, $interestRate, $durationMonths)
            : round($financedAmount / $durationMonths, 2);

        $totalCost  = round($monthlyInstallment * $durationMonths, 2);
        $startDate  = \DateTimeImmutable::createFromInterface($a->getDateAchat());

        $firstPayDate = !empty($data['dateDebutCredit'])
            ? new \DateTimeImmutable($data['dateDebutCredit'])
            : $startDate->modify('+1 month');

        $dueDay  = (int) $firstPayDate->format('d');
        $endDate = $startDate->modify("+{$durationMonths} months");

        $vc = new VehicleCredit();
        $vc->setVoiture($a->getVoiture());
        $vc->setSupplier($a->getFournisseur());
        $vc->setVehiclePrice((string) $vehiclePrice);
        $vc->setDownPayment((string) $downPayment);
        $vc->setFinancedAmount((string) $financedAmount);
        $vc->setInterestRate((string) $interestRate);
        $vc->setDurationMonths($durationMonths);
        $vc->setMonthlyInstallment((string) $monthlyInstallment);
        $vc->setTotalCost((string) $totalCost);
        $vc->setStartDate($startDate);
        $vc->setEndDate($endDate);
        $vc->setFirstPaymentDate($firstPayDate);
        $vc->setDueDay($dueDay);
        $vc->setRemainingBalance((string) $totalCost);
        $vc->setStatus('active');

        $em->persist($vc);

        $r           = $interestRate > 0 ? ($interestRate / 100) / 12 : 0.0;
        $balance     = $financedAmount;
        $currentDate = $firstPayDate;
        $today       = new \DateTimeImmutable('today');

        for ($n = 1; $n <= $durationMonths; $n++) {
            $interest      = $r > 0 ? round($balance * $r, 2) : 0.0;
            $principalPart = round($monthlyInstallment - $interest, 2);
            $amountDue     = $monthlyInstallment;

            if ($n === $durationMonths) {
                $principalPart = round($balance, 2);
                $amountDue     = round($principalPart + $interest, 2);
            }

            $balance = max(0.0, round($balance - $principalPart, 2));

            $maxDay  = (int) $currentDate->format('t');
            $dueDate = new \DateTimeImmutable(
                $currentDate->format('Y-m') . '-' . str_pad((string) min($dueDay, $maxDay), 2, '0', STR_PAD_LEFT)
            );

            $inst = new VehicleCreditInstallment();
            $inst->setVehicleCredit($vc);
            $inst->setInstallmentNumber($n);
            $inst->setDueDate($dueDate);
            $inst->setPrincipalAmount((string) $principalPart);
            $inst->setInterestAmount((string) $interest);
            $inst->setAmountDue((string) $amountDue);
            $inst->setAmountPaid('0');
            $inst->setRemainingAmount((string) $amountDue);
            $inst->setStatus('pending');

            $em->persist($inst);

            foreach ([[-5, '5_days'], [-3, '3_days'], [0, 'due_today']] as [$days, $type]) {
                $reminderDate = $dueDate->modify("{$days} days");
                if ($reminderDate < $today) continue;

                $reminder = new VehicleCreditReminder();
                $reminder->setVehicleCredit($vc);
                $reminder->setInstallment($inst);
                $reminder->setReminderDate($reminderDate);
                $reminder->setReminderType($type);
                $reminder->setStatus('pending');
                $em->persist($reminder);
            }

            $currentDate = $currentDate->modify('+1 month');
        }
    }

    private function calcMonthlyInstallment(float $principal, float $annualRate, int $months): float
    {
        $r = ($annualRate / 100) / 12;
        return round($principal * ($r * pow(1 + $r, $months)) / (pow(1 + $r, $months) - 1), 2);
    }

    private function calcAnnualRate(float $principal, float $monthly, int $months): float
    {
        if ($monthly * $months <= $principal) return 0.0;
        $r = 0.01;
        for ($i = 0; $i < 200; $i++) {
            $pow  = pow(1 + $r, $months);
            $powP = pow(1 + $r, $months + 1);
            $powM = pow(1 + $r, $months - 1);
            $f    = $principal * $r * $pow / ($pow - 1) - $monthly;
            $fp   = $principal * $powM * ($powP - 1 - $r * ($months + 1)) / pow($pow - 1, 2);
            if (abs($fp) < 1e-15) break;
            $rNew = $r - $f / $fp;
            if ($rNew <= 0) $rNew = $r / 2;
            if (abs($rNew - $r) < 1e-10) { $r = $rNew; break; }
            $r = $rNew;
        }
        return $r > 0 ? round($r * 12 * 100, 4) : 0.0;
    }
}
