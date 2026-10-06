<?php

namespace App\Controller\Api;

use App\Entity\Assurance;
use App\Entity\Depense;
use App\Enum\StatusEnum;
use App\Repository\AssuranceRepository;
use App\Repository\DepenseRepository;
use App\Repository\PaiementDepenseRepository;
use App\Repository\VoitureRepository;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Service\ActivityLogService;
use App\Service\DepensePaymentService;
use App\Service\VoitureStatusService;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/assurance', name: 'app_api_assurance_')]
class AssuranceController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementDepenseRepository $paieRepo,
        private DepensePaymentService     $paymentService,
        private VoitureStatusService      $statusService,
        private AssuranceRepository       $assuranceRepo,
        private ActivityLogService        $activityLog,
        private FleetLifecycleManager     $flm,
    ) {}

    /** Bureau-locked staff/managers may only touch insurance records belonging to
     *  their own bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(Assurance $assurance): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($assurance->getDepense()?->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Assurance introuvable');
        }
    }

    private function snapshotAssurance(Assurance $a): array
    {
        $dep = $a->getDepense();
        return [
            'voitureId'     => $dep?->getVoiture()?->getId(),
            'dateDebut'     => $dep?->getDateDebut()?->format('Y-m-d'),
            'dateFin'       => $dep?->getDateFin()?->format('Y-m-d'),
            'compagnie'     => $a->getCompagnie(),
            'typeAssurance' => $a->getTypeAssurance(),
            'numeroContrat' => $a->getNumeroContrat(),
            'montant'       => $dep?->getMontant(),
        ];
    }

    private function serialize(Assurance $a): array
    {
        $dep  = $a->getDepense();
        $voit = $dep?->getVoiture();
        $now  = new \DateTimeImmutable();

        $today  = $now->format('Y-m-d');
        $status = match(true) {
            $a->getCancelledAt() !== null                                                     => 'cancelled',
            $a->getArchivedAt() !== null                                                      => 'archived',
            $dep?->getDateFin() !== null && $dep->getDateFin()->format('Y-m-d') < $today     => 'expired',
            $dep?->getDateDebut() !== null && $dep->getDateDebut()->format('Y-m-d') > $today => 'upcoming',
            default                                                                          => 'active',
        };

        return [
            'id'            => $a->getId(),
            'dateDebut'     => $dep?->getDateDebut()?->format('Y-m-d'),
            'dateFin'       => $dep?->getDateFin()?->format('Y-m-d'),
            'compagnie'     => $a->getCompagnie(),
            'typeAssurance' => $a->getTypeAssurance(),
            'numeroContrat' => $a->getNumeroContrat(),
            'depenseId'     => $dep?->getId(),
            'voitureId'     => $voit?->getId(),
            'voiture'       => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'montant'       => $dep?->getMontant(),
            'montantTotal'  => $dep?->getMontant() ? (float) $dep->getMontant() : 0.0,
            'montantPaye'   => $dep?->getMontantPaye() ? (float) $dep->getMontantPaye() : 0.0,
            'reste'         => DepensePaymentService::computeReste((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paymentStatut' => DepensePaymentService::paymentStatutLabel((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paiementCount' => $dep ? $this->paieRepo->countByDepense($dep->getId()) : 0,
            'creeAu'        => $a->getCreeAu()?->format('Y-m-d'),
            'dateFacture'   => $dep?->getDateFacture()?->format('Y-m-d'),
            'archivedAt'    => $a->getArchivedAt()?->format('Y-m-d'),
            'cancelledAt'   => $a->getCancelledAt()?->format('Y-m-d'),
            'status'        => $status,
            'filePath'      => $a->getFilePath(),
            'notes'         => $a->getNotes(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId     = $this->getEffectiveBureauId();
        $voitureId    = (int) $request->query->get('voitureId', 0);
        $showArchived = $request->query->getBoolean('archived', false);
        $historyOnly  = $request->query->getBoolean('historyOnly', false);
        $page         = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('a')
            ->from(Assurance::class, 'a')
            ->join('a.depense', 'd')
            ->join('d.voiture', 'v')
            ->where('a.deletedAt IS NULL')
            ->orderBy('d.dateDebut', 'ASC');

        if ($bureauId) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }
        if ($historyOnly) {
            // Only superseded/cancelled records — never the current policy
            $qb->andWhere('a.archivedAt IS NOT NULL OR a.cancelledAt IS NOT NULL')
               ->orderBy('d.dateDebut', 'DESC');
        } elseif (!$showArchived) {
            $qb->andWhere('a.archivedAt IS NULL')->andWhere('a.cancelledAt IS NULL');
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map(fn($a) => $this->serialize($a), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Assurance $assurance): JsonResponse
    {
        $this->assertBureauAccess($assurance);
        return $this->json($this->serialize($assurance));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $dateDebut = !empty($data['dateDebut']) ? new \DateTimeImmutable($data['dateDebut']) : new \DateTimeImmutable();
        $dateFin   = !empty($data['dateFin']) ? new \DateTimeImmutable($data['dateFin']) : null;

        // Resolve voiture before persisting anything so the overlap check can run first
        $voiture = null;
        $depense = null;
        if (!empty($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
            $voiture = $depense?->getVoiture();
        } elseif (!empty($data['voitureId'])) {
            $voiture = $voitureRepo->find((int) $data['voitureId']);
        }

        if ($voiture) {
            if (!empty($data['depenseId']) && $dateFin) {
                // Depense-linked path: strict overlap check
                if ($this->assuranceRepo->hasOverlap($voiture, $dateDebut, $dateFin)) {
                    return $this->json(['error' => 'Une assurance active chevauche déjà cette période.'], 422);
                }
            } else {
                // Direct voitureId path: auto-archive any existing active policy
                $existing = $this->assuranceRepo->findActiveByVoiture($voiture);
                if ($existing) {
                    $existing->setArchivedAt(new \DateTimeImmutable());
                    $em->persist($existing);
                }
            }
        }

        if (!$depense && $voiture) {
            $depense = new Depense();
            $depense->setDateDebut($dateDebut);
            $depense->setDateFin($dateFin);
            if (!empty($data['dateFacture'])) $depense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
            $depense->setTypeDepense('assurance');
            $depense->setMontant((string) ($data['montant'] ?? 0));
            $depense->setMontantPaye('0');
            $depense->setStatut(StatusEnum::IMPAYE);
            $depense->setVoiture($voiture);
            if ($voiture->getBureau()) $depense->setBureau($voiture->getBureau());
            $depense->setCreeAu(new \DateTimeImmutable());
            $depense->setCreePar($this->getUser());
            $em->persist($depense);
        }

        $assurance = new Assurance();
        if ($depense) $assurance->setDepense($depense);
        $assurance->setCompagnie($data['compagnie'] ?? null);
        $assurance->setTypeAssurance($data['typeAssurance'] ?? null);
        $assurance->setNumeroContrat($data['numeroContrat'] ?? null);
        $assurance->setNotes($data['notes'] ?? null);
        $assurance->setCreeAu(new \DateTimeImmutable());
        $assurance->setCreePar($this->getUser());
        $em->persist($assurance);
        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($depense && $montantPaye > 0) {
            $this->paymentService->createInitialPayment($depense, $montantPaye, $em, $this->getUser());
        }

        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        $this->activityLog->logCreate('Assurance', $assurance->getId(), $this->snapshotAssurance($assurance), $voiture?->getBureau());

        return $this->json(['message' => 'Assurance créée', 'id' => $assurance->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Assurance $assurance, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($assurance);
        $data    = json_decode($request->getContent(), true);
        $oldSnap = $this->snapshotAssurance($assurance);

        if (isset($data['dateDebut']) && $assurance->getDepense())     $assurance->getDepense()->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (isset($data['dateFin']) && $assurance->getDepense())       $assurance->getDepense()->setDateFin(new \DateTimeImmutable($data['dateFin']));
        if (array_key_exists('compagnie', $data))     $assurance->setCompagnie($data['compagnie'] ?: null);
        if (array_key_exists('typeAssurance', $data)) $assurance->setTypeAssurance($data['typeAssurance'] ?: null);
        if (isset($data['numeroContrat'])) $assurance->setNumeroContrat($data['numeroContrat']);
        if (array_key_exists('dateFacture', $data) && $assurance->getDepense()) {
            $assurance->getDepense()->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        if (array_key_exists('filePath', $data)) $assurance->setFilePath($data['filePath'] ?: null);
        if (array_key_exists('notes', $data)) $assurance->setNotes($data['notes'] ?: null);
        $assurance->setEditAu(new \DateTimeImmutable());

        if (isset($data['montant']) && $assurance->getDepense()) {
            $assurance->getDepense()->setMontant((string) $data['montant']);
            $this->paymentService->recalculate($assurance->getDepense()->getId(), $em);
        }

        $em->flush();

        $voiture = $assurance->getDepense()?->getVoiture();
        $this->activityLog->logUpdate('Assurance', $assurance->getId(), $oldSnap, $this->snapshotAssurance($assurance), $voiture?->getBureau());
        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Assurance mise à jour']);
    }

    #[Route('/{id}/renew', name: 'renew', methods: ['POST'])]
    public function renew(
        Assurance $assurance,
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $this->assertBureauAccess($assurance);
        $data = json_decode($request->getContent(), true);

        if ($assurance->getDeletedAt() !== null) {
            return $this->json(['error' => 'Assurance supprimée.'], 404);
        }
        if ($assurance->getArchivedAt() !== null) {
            return $this->json(['error' => 'Cette assurance est déjà archivée.'], 422);
        }

        $dep = $assurance->getDepense();
        if (!$dep) {
            return $this->json(['error' => 'Assurance sans dépense associée.'], 422);
        }

        $voiture = $dep->getVoiture();
        if (!$voiture) {
            return $this->json(['error' => 'Voiture introuvable.'], 422);
        }

        $newDebut = new \DateTimeImmutable($data['dateDebut']);
        $newFin   = new \DateTimeImmutable($data['dateFin']);

        if ($this->assuranceRepo->hasOverlap($voiture, $newDebut, $newFin)) {
            return $this->json(['error' => 'Une assurance active chevauche déjà cette période.'], 422);
        }

        // Archive old
        $assurance->setArchivedAt(new \DateTimeImmutable());
        $assurance->setEditAu(new \DateTimeImmutable());

        // New depense
        $newDepense = new Depense();
        $newDepense->setDateDebut($newDebut);
        $newDepense->setDateFin($newFin);
        if (!empty($data['dateFacture'])) $newDepense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
        $newDepense->setTypeDepense('assurance');
        $newDepense->setMontant((string) ($data['montant'] ?? 0));
        $newDepense->setMontantPaye('0');
        $newDepense->setStatut(StatusEnum::IMPAYE);
        $newDepense->setVoiture($voiture);
        if ($voiture->getBureau()) $newDepense->setBureau($voiture->getBureau());
        $newDepense->setCreeAu(new \DateTimeImmutable());
        $newDepense->setCreePar($this->getUser());
        $em->persist($newDepense);

        // New assurance
        $newAssurance = new Assurance();
        $newAssurance->setDepense($newDepense);
        $newAssurance->setCompagnie($data['compagnie'] ?? $assurance->getCompagnie());
        $newAssurance->setTypeAssurance($data['typeAssurance'] ?? $assurance->getTypeAssurance());
        $newAssurance->setNumeroContrat($data['numeroContrat'] ?? null);
        $newAssurance->setCreeAu(new \DateTimeImmutable());
        $newAssurance->setCreePar($this->getUser());
        $em->persist($newAssurance);

        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($montantPaye > 0) {
            $this->paymentService->createInitialPayment($newDepense, $montantPaye, $em, $this->getUser());
        }

        $this->activityLog->logArchive('Assurance', $assurance->getId(), $this->snapshotAssurance($assurance), $voiture->getBureau());
        $this->activityLog->logCreate('Assurance', $newAssurance->getId(), $this->snapshotAssurance($newAssurance), $voiture->getBureau());

        $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json([
            'message'      => 'Renouvellement effectué.',
            'archivedId'   => $assurance->getId(),
            'newId'        => $newAssurance->getId(),
            'newDepenseId' => $newDepense->getId(),
        ], 201);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Assurance $assurance, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($assurance);
        if ($assurance->getCancelledAt() !== null) {
            return $this->json(['error' => 'Cette assurance est déjà annulée.'], 422);
        }
        if ($assurance->getArchivedAt() !== null) {
            return $this->json(['error' => 'Cette assurance est déjà archivée.'], 422);
        }

        $oldSnap = $this->snapshotAssurance($assurance);
        $assurance->setCancelledAt(new \DateTimeImmutable());
        $assurance->setEditAu(new \DateTimeImmutable());
        $em->flush();

        $voiture = $assurance->getDepense()?->getVoiture();
        $this->activityLog->logUpdate('Assurance', $assurance->getId(), $oldSnap, $this->snapshotAssurance($assurance), $voiture?->getBureau());
        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Assurance annulée']);
    }

    #[Route('/{id}/file', name: 'upload_file', methods: ['POST'])]
    public function uploadFile(Assurance $assurance, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($assurance);
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier'], 400);
        }

        $ext      = $file->guessExtension() ?? 'bin';
        $filename = uniqid('ass_') . '.' . $ext;
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/compliance/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $file->move($uploadDir, $filename);
        $assurance->setFilePath('/uploads/compliance/' . $filename);
        $em->flush();

        return $this->json(['filePath' => $assurance->getFilePath()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Assurance $assurance, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($assurance);
        $dep = $assurance->getDepense();
        if ($dep && $this->paieRepo->countByDepense($dep->getId()) > 0) {
            return $this->json([
                'error'   => 'has_payments',
                'message' => 'Cette assurance a des paiements enregistrés et ne peut pas être supprimée. Utilisez Annuler à la place.',
            ], 422);
        }

        $voiture = $assurance->getDepense()?->getVoiture();
        $snap    = $this->snapshotAssurance($assurance);
        $id      = $assurance->getId();
        $assurance->setDeletedAt(new \DateTimeImmutable());
        if ($assurance->getDepense()) {
            $assurance->getDepense()->setDeletedAt(new \DateTimeImmutable());
        }
        $em->flush();

        $this->activityLog->logDelete('Assurance', $id, $snap, $voiture?->getBureau());
        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Assurance supprimée'], 200);
    }
}
