<?php

namespace App\Controller\Api;

use App\Entity\Bureau;
use App\Entity\Depense;
use App\Repository\BureauRepository;
use App\Repository\VoitureRepository;
use App\Service\ActivityLogService;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Enum\StatusEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api/depense', name: 'app_api_depense_')]
#[IsGranted('ROLE_USER')]
class DepenseController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    private const REPETITIVE_TYPES = [
        'loyer', 'salaire', 'telephone', 'electricite', 'eau', 'internet', 'vignette',
    ];

    public function __construct(private ActivityLogService $activityLog) {}

    private function snapshotDepense(Depense $d): array
    {
        return [
            'voitureId'   => $d->getVoiture()?->getId(),
            'bureauId'    => $d->getBureau()?->getId(),
            'dateDebut'   => $d->getDateDebut()?->format('Y-m-d'),
            'dateFin'     => $d->getDateFin()?->format('Y-m-d'),
            'typeDepense' => $d->getTypeDepense(),
            'description' => $d->getDescription(),
            'montant'     => $d->getMontant(),
            'statut'      => $d->getStatut()?->value,
            'dateFacture' => $d->getDateFacture()?->format('Y-m-d'),
        ];
    }

    // ─── Summary (must be before /{id} routes) ────────────────────────────────

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $effectiveBureauId = $this->getEffectiveBureauId();
        $filterBureauId    = $request->query->get('bureauId');
        $now               = new \DateTimeImmutable('today');
        $cm                = (int) $now->format('n');
        $cy                = (int) $now->format('Y');
        $bureauId          = $effectiveBureauId ?? ($filterBureauId ? (int) $filterBureauId : null);

        $applyBureau = function (\Doctrine\ORM\QueryBuilder $qb) use ($bureauId): void {
            if ($bureauId) {
                $qb->andWhere('d.bureau = :bid')->setParameter('bid', $bureauId);
            }
        };

        // "This period": use periodMonth/Year when set, else fall back to dateDebut
        $periodCond = '(d.periodMonth IS NOT NULL AND d.periodMonth = :cm AND d.periodYear = :cy)'
            . ' OR (d.periodMonth IS NULL AND MONTH(d.dateDebut) = :cm AND YEAR(d.dateDebut) = :cy)';

        // "This year": prefer periodYear to avoid double-counting cross-month auto-generated entries
        $yearCond = '(d.periodYear IS NOT NULL AND d.periodYear = :cy)'
            . ' OR (d.periodYear IS NULL AND YEAR(d.dateDebut) = :cy)';

        $makeBase = fn () => $em->createQueryBuilder()
            ->from(Depense::class, 'd')
            ->where('d.deletedAt IS NULL AND d.voiture IS NULL');

        // 1. This month — planned
        $q1 = $makeBase()->select('COALESCE(SUM(d.montant), 0)')
            ->andWhere($periodCond)
            ->setParameter('cm', $cm)->setParameter('cy', $cy);
        $applyBureau($q1);
        $thisMonthPlanned = (float) ($q1->getQuery()->getSingleScalarResult() ?? 0);

        // 2. This month — paid
        $q2 = $makeBase()->select('COALESCE(SUM(d.montant), 0)')
            ->andWhere($periodCond)->andWhere('d.statut = :payee')
            ->setParameter('cm', $cm)->setParameter('cy', $cy)->setParameter('payee', StatusEnum::PAYEE);
        $applyBureau($q2);
        $thisMonthPaid = (float) ($q2->getQuery()->getSingleScalarResult() ?? 0);

        // 3. This month — pending
        $q3 = $makeBase()->select('COALESCE(SUM(d.montant), 0)')
            ->andWhere($periodCond)->andWhere('d.statut = :pend')
            ->setParameter('cm', $cm)->setParameter('cy', $cy)->setParameter('pend', StatusEnum::PENDING);
        $applyBureau($q3);
        $thisMonthPending = (float) ($q3->getQuery()->getSingleScalarResult() ?? 0);

        // 4. Year total
        $q4 = $makeBase()->select('COALESCE(SUM(d.montant), 0)')
            ->andWhere($yearCond)->setParameter('cy', $cy);
        $applyBureau($q4);
        $yearTotal = (float) ($q4->getQuery()->getSingleScalarResult() ?? 0);

        // 5. Overdue: pending past their effective period
        $overdueWhere = 'd.statut = :pend AND ('
            . '(d.periodMonth IS NOT NULL AND (d.periodYear < :cy OR (d.periodYear = :cy AND d.periodMonth < :cm)))'
            . ' OR (d.periodMonth IS NULL AND d.dateDebut < :today)'
            . ')';
        $q5 = $makeBase()->select('COUNT(d.id) as cnt, COALESCE(SUM(d.montant), 0) as total')
            ->andWhere($overdueWhere)
            ->setParameter('pend', StatusEnum::PENDING)
            ->setParameter('cm', $cm)->setParameter('cy', $cy)->setParameter('today', $now);
        $applyBureau($q5);
        $ov = $q5->getQuery()->getSingleResult();

        return $this->json([
            'thisMonthPlanned' => $thisMonthPlanned,
            'thisMonthPaid'    => $thisMonthPaid,
            'thisMonthPending' => $thisMonthPending,
            'yearTotal'        => $yearTotal,
            'overdueCount'     => (int) ($ov['cnt'] ?? 0),
            'overdueAmount'    => (float) ($ov['total'] ?? 0),
        ]);
    }

    // ─── List ─────────────────────────────────────────────────────────────────

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $effectiveBureauId   = $this->getEffectiveBureauId();
        $page                = $this->getPageParam($request);
        $search              = trim((string) $request->query->get('search', ''));
        $type                = $request->query->get('type');
        $filterStatut        = $request->query->get('statut');
        $filterType          = $request->query->get('typeDepense');
        $filterMonth         = $request->query->get('month');
        $filterYear          = $request->query->get('year');
        $filterBureauId      = $request->query->get('bureauId');
        $filterRecurringFreq = $request->query->get('recurringFrequency');

        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(Depense::class, 'd')
            ->leftJoin('d.voiture', 'v')
            ->leftJoin('v.bureau', 'vb')
            ->leftJoin('d.bureau', 'b')
            ->leftJoin('d.recurringTemplate', 'rt')
            ->where('d.deletedAt IS NULL')
            ->orderBy('d.dateDebut', 'DESC');

        if ($type === 'vehicle') {
            $qb->andWhere('d.voiture IS NOT NULL');
        } elseif ($type === 'bureau') {
            $qb->andWhere('d.voiture IS NULL');
        }

        // Bureau scoping: user's bureau takes priority over admin filter
        if ($effectiveBureauId) {
            if ($type === 'vehicle') {
                $qb->andWhere('vb.id = :bureauId')->setParameter('bureauId', $effectiveBureauId);
            } elseif ($type === 'bureau') {
                $qb->andWhere('b.id = :bureauId')->setParameter('bureauId', $effectiveBureauId);
            } else {
                $qb->andWhere('b.id = :bureauId OR vb.id = :bureauId')->setParameter('bureauId', $effectiveBureauId);
            }
        } elseif ($filterBureauId) {
            if ($type === 'vehicle') {
                $qb->andWhere('vb.id = :filterBureauId')->setParameter('filterBureauId', (int) $filterBureauId);
            } elseif ($type === 'bureau') {
                $qb->andWhere('b.id = :filterBureauId')->setParameter('filterBureauId', (int) $filterBureauId);
            } else {
                $qb->andWhere('b.id = :filterBureauId OR vb.id = :filterBureauId')->setParameter('filterBureauId', (int) $filterBureauId);
            }
        }

        if ($search) {
            $qb->andWhere('d.typeDepense LIKE :s OR d.description LIKE :s')
               ->setParameter('s', "%$search%");
        }

        if ($filterStatut) {
            try {
                $statusEnum = StatusEnum::from($filterStatut);
                $qb->andWhere('d.statut = :statut')->setParameter('statut', $statusEnum);
            } catch (\ValueError) {}
        }

        if ($filterType) {
            $qb->andWhere('d.typeDepense = :ftype')->setParameter('ftype', $filterType);
        }

        if ($filterYear) {
            $qb->andWhere('YEAR(d.dateDebut) = :fyear')->setParameter('fyear', (int) $filterYear);
        }

        if ($filterMonth) {
            $qb->andWhere('MONTH(d.dateDebut) = :fmonth')->setParameter('fmonth', (int) $filterMonth);
        }

        if ($filterRecurringFreq) {
            $repeatTypes = self::REPETITIVE_TYPES;
            if ($filterRecurringFreq === 'monthly') {
                $qb->andWhere('(rt.frequency = :rfreq) OR (d.recurringTemplate IS NULL AND d.typeDepense IN (:rtypes))')
                   ->setParameter('rfreq', 'monthly')
                   ->setParameter('rtypes', $repeatTypes);
            } elseif ($filterRecurringFreq === 'yearly') {
                $qb->andWhere('rt.frequency = :rfreq')->setParameter('rfreq', 'yearly');
            } elseif ($filterRecurringFreq === 'one_time') {
                $qb->andWhere('d.recurringTemplate IS NULL AND d.typeDepense NOT IN (:rtypes)')
                   ->setParameter('rtypes', $repeatTypes);
            }
        }

        $serialize = fn (Depense $d) => [
            'id'                  => $d->getId(),
            'dateDebut'           => $d->getDateDebut()?->format('Y-m-d'),
            'dateFin'             => $d->getDateFin()?->format('Y-m-d'),
            'typeDepense'         => $d->getTypeDepense(),
            'description'         => $d->getDescription(),
            'montant'             => $d->getMontant(),
            'montantPaye'         => $d->getMontantPaye(),
            'statut'              => $d->getStatut()?->value,
            'datePaiement'        => $d->getDatePaiement()?->format('Y-m-d'),
            'dateFacture'         => $d->getDateFacture()?->format('Y-m-d'),
            'voitureId'           => $d->getVoiture()?->getId(),
            'voitureLabel'        => $d->getVoiture()
                ? ($d->getVoiture()->getMarque().' '.$d->getVoiture()->getModele().' · '.$d->getVoiture()->getImmatriculation())
                : null,
            'bureauId'            => $d->getBureau()?->getId(),
            'bureauNom'           => $d->getBureau()?->getNom(),
            'creeAu'              => $d->getCreeAu()?->format('Y-m-d H:i:s'),
            'justificatifUrl'     => $d->getJustificatifName()
                ? '/uploads/depense-documents/'.$d->getJustificatifName()
                : null,
            'isAutoGenerated'     => $d->isAutoGenerated(),
            'dismissedAt'         => $d->getDismissedAt()?->format('Y-m-d H:i:s'),
            'periodMonth'         => $d->getPeriodMonth(),
            'periodYear'          => $d->getPeriodYear(),
            'recurringTemplateId' => $d->getRecurringTemplate()?->getId(),
            'templatePriceType'   => $d->getRecurringTemplate()?->getPriceType(),
            'templateFrequency'   => $d->getRecurringTemplate()?->getFrequency(),
        ];

        [$depenses, $total] = $this->paginateQb($qb, $page);

        return $this->json([
            'data' => array_map($serialize, $depenses),
            'meta' => $this->paginateMeta($total, $page),
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Depense $depense): JsonResponse
    {
        return $this->json([
            'id'              => $depense->getId(),
            'dateDebut'       => $depense->getDateDebut()?->format('Y-m-d'),
            'dateFin'         => $depense->getDateFin()?->format('Y-m-d'),
            'typeDepense'     => $depense->getTypeDepense(),
            'description'     => $depense->getDescription(),
            'montant'         => $depense->getMontant(),
            'montantPaye'     => $depense->getMontantPaye(),
            'statut'          => $depense->getStatut()?->value,
            'datePaiement'    => $depense->getDatePaiement()?->format('Y-m-d'),
            'dateFacture'     => $depense->getDateFacture()?->format('Y-m-d'),
            'voiture'         => $depense->getVoiture()?->getId(),
            'bureau'          => $depense->getBureau()?->getId(),
            'creePar'         => $depense->getCreePar()?->getId(),
            'creeAu'          => $depense->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'          => $depense->getEditAu()?->format('Y-m-d H:i:s'),
            'justificatifUrl' => $depense->getJustificatifName()
                ? '/uploads/depense-documents/'.$depense->getJustificatifName()
                : null,
            'templatePriceType' => $depense->getRecurringTemplate()?->getPriceType(),
            'templateFrequency' => $depense->getRecurringTemplate()?->getFrequency(),
        ]);
    }

    // ─── Upload justificatif ──────────────────────────────────────────────────

    #[Route('/{id}/justificatif', name: 'upload_justificatif', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function uploadJustificatif(Depense $depense, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return $this->json(['error' => 'Only PDF, JPEG, PNG or WebP files are accepted'], 415);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'File exceeds 10 MB limit'], 413);
        }

        $depense->setJustificatifFile($file);
        $em->flush();

        return $this->json([
            'justificatifUrl' => '/uploads/depense-documents/'.$depense->getJustificatifName(),
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        BureauRepository $bureauRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $depense = new Depense();
        $depense->setDateDebut(new \DateTimeImmutable($data['dateDebut'] ?? $data['date'] ?? 'now'));
        if (!empty($data['dateFin'])) $depense->setDateFin(new \DateTimeImmutable($data['dateFin']));
        $depense->setTypeDepense($data['typeDepense']);
        $depense->setDescription($data['description'] ?? null);
        $depense->setMontant($data['montant']);

        try {
            $depense->setStatut(isset($data['statut']) ? StatusEnum::from($data['statut']) : StatusEnum::PENDING);
        } catch (\ValueError) {
            return $this->json(['message' => 'Statut invalide'], 400);
        }

        $depense->setDatePaiement(!empty($data['datePaiement']) ? new \DateTimeImmutable($data['datePaiement']) : null);
        if (!empty($data['dateFacture'])) $depense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
        $depense->setCreeAu(new \DateTimeImmutable());
        $depense->setCreePar($this->getUser());

        // Period (for recurring expenses)
        if (isset($data['periodMonth'])) {
            $depense->setPeriodMonth($data['periodMonth'] !== null ? (int) $data['periodMonth'] : null);
        }
        if (isset($data['periodYear'])) {
            $depense->setPeriodYear($data['periodYear'] !== null ? (int) $data['periodYear'] : null);
        }

        if (!empty($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
            if ($voiture) {
                $depense->setVoiture($voiture);
                if (($data['typeDepense'] ?? '') === 'suivi_technique' && $voiture->getAnnee() !== null) {
                    $vehicleAge = (int) (new \DateTimeImmutable())->format('Y') - $voiture->getAnnee();
                    if ($vehicleAge < 3) {
                        return $this->json([
                            'message' => 'Technical inspection cannot be registered for a vehicle less than 3 years old.',
                        ], 422);
                    }
                }
            }
        }

        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if ($user->getBureau()) {
            $depense->setBureau($user->getBureau());
        } elseif (!empty($data['bureauId'])) {
            $bureau = $bureauRepo->find($data['bureauId']);
            if ($bureau) $depense->setBureau($bureau);
        }

        $em->persist($depense);
        $em->flush();

        $this->activityLog->logCreate('Depense', $depense->getId(), $this->snapshotDepense($depense), $depense->getBureau());

        return $this->json(['message' => 'Dépense créée', 'id' => $depense->getId()], 201);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Depense $depense, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $oldSnap = $this->snapshotDepense($depense);

        if (isset($data['dateDebut']))    $depense->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (array_key_exists('dateFin', $data)) {
            $depense->setDateFin(!empty($data['dateFin']) ? new \DateTimeImmutable($data['dateFin']) : null);
        }
        if (isset($data['typeDepense'])) $depense->setTypeDepense($data['typeDepense']);
        if (isset($data['description']))  $depense->setDescription($data['description']);
        if (isset($data['montant']))      $depense->setMontant($data['montant']);

        if (array_key_exists('montantPaye', $data)) {
            $mp = $data['montantPaye'];
            $depense->setMontantPaye(($mp !== null && $mp !== '') ? (string) $mp : null);
        }

        if (isset($data['statut'])) {
            try {
                $depense->setStatut(StatusEnum::from($data['statut']));
            } catch (\ValueError) {
                return $this->json(['message' => 'Statut invalide'], 400);
            }
        }

        if (isset($data['datePaiement'])) {
            $depense->setDatePaiement(!empty($data['datePaiement']) ? new \DateTimeImmutable($data['datePaiement']) : null);
        }
        if (array_key_exists('dateFacture', $data)) {
            $depense->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        $depense->setEditAu(new \DateTimeImmutable());

        // Update template fixed price if user opted in
        if (!empty($data['updateTemplatePrice']) && isset($data['montant'])) {
            $template = $depense->getRecurringTemplate();
            if ($template && $template->getPriceType() === 'fixed') {
                $template->setFixedAmount((string) $data['montant']);
            }
        }

        $em->flush();

        $this->activityLog->logUpdate('Depense', $depense->getId(), $oldSnap, $this->snapshotDepense($depense), $depense->getBureau());

        return $this->json(['message' => 'Dépense mise à jour']);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Depense $depense, EntityManagerInterface $em): JsonResponse
    {
        $snap   = $this->snapshotDepense($depense);
        $id     = $depense->getId();
        $bureau = $depense->getBureau();
        $depense->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        $this->activityLog->logDelete('Depense', $id, $snap, $bureau);

        return $this->json(['message' => 'Dépense supprimée']);
    }

    // ─── Dismiss ──────────────────────────────────────────────────────────────

    #[Route('/{id}/dismiss', name: 'dismiss', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dismiss(Depense $depense, EntityManagerInterface $em): JsonResponse
    {
        if (!$depense->isAutoGenerated()) {
            return $this->json(['message' => 'Not an auto-generated expense'], 422);
        }
        $depense->setDismissedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Dismissed']);
    }
}
