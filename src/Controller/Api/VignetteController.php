<?php

namespace App\Controller\Api;

use App\Entity\Vignette;
use App\Entity\Depense;
use App\Enum\StatusEnum;
use App\Repository\VignetteRepository;
use App\Repository\DepenseRepository;
use App\Repository\PaiementDepenseRepository;
use App\Repository\VoitureRepository;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Service\ActivityLogService;
use App\Service\DepensePaymentService;
use App\Service\VoitureStatusService;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vignette', name: 'app_api_vignette_')]
#[IsGranted('ROLE_USER')]
class VignetteController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementDepenseRepository $paieRepo,
        private DepensePaymentService     $paymentService,
        private VoitureStatusService      $statusService,
        private ActivityLogService        $activityLog,
        private FleetLifecycleManager     $flm,
    ) {}

    /** Bureau-locked staff/managers may only touch vignette records belonging to
     *  their own bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(Vignette $vignette): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($vignette->getDepense()?->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Vignette introuvable');
        }
    }

    private function snapshotVignette(Vignette $v): array
    {
        $dep = $v->getDepense();
        return [
            'voitureId'  => $dep?->getVoiture()?->getId(),
            'annee'      => $v->getAnnee(),
            'dateLimite' => $v->getDepense()?->getDateFin()?->format('Y-m-d'),
            'montant'    => $dep?->getMontant(),
        ];
    }

    private function serialize(Vignette $v): array
    {
        $dep  = $v->getDepense();
        $voit = $dep?->getVoiture();
        return [
            'id'            => $v->getId(),
            'annee'         => $v->getAnnee(),
            'dateLimite'    => $dep?->getDateFin()?->format('Y-m-d'),
            'datePaiement'  => $dep?->getDateFin()?->format('Y-m-d'),
            'depenseId'     => $dep?->getId(),
            'voitureId'     => $voit?->getId(),
            'voiture'       => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'montant'       => $dep?->getMontant(),
            'montantTotal'  => $dep?->getMontant() ? (float) $dep->getMontant() : 0.0,
            'montantPaye'   => $dep?->getMontantPaye() ? (float) $dep->getMontantPaye() : 0.0,
            'reste'         => DepensePaymentService::computeReste((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paymentStatut' => DepensePaymentService::paymentStatutLabel((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paiementCount' => $dep ? $this->paieRepo->countByDepense($dep->getId()) : 0,
            'creeAu'        => $v->getCreeAu()?->format('Y-m-d'),
            'dateFacture'   => $dep?->getDateFacture()?->format('Y-m-d'),
            'filePath'      => $v->getFilePath(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('vg')
            ->from(Vignette::class, 'vg')
            ->join('vg.depense', 'd')
            ->join('d.voiture', 'v')
            ->where('vg.deletedAt IS NULL')
            ->orderBy('d.dateFin', 'DESC');

        if ($bureauId !== null) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        $voitureId = (int) $request->query->get('voitureId', 0);
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map(fn($v) => $this->serialize($v), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Vignette $vignette): JsonResponse
    {
        $this->assertBureauAccess($vignette);
        return $this->json($this->serialize($vignette));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Resolve voiture for uniqueness check
        $voiture = null;
        if (!empty($data['voitureId'])) {
            $voiture = $voitureRepo->find((int) $data['voitureId']);
        } elseif (!empty($data['depenseId'])) {
            $existingDep = $depenseRepo->find($data['depenseId']);
            $voiture = $existingDep?->getVoiture();
        }

        // Uniqueness: block if a non-deleted vignette already exists for this vehicle + same dateLimite year
        if ($voiture && !empty($data['dateLimite'])) {
            $dlDate    = new \DateTimeImmutable($data['dateLimite']);
            $year      = (int) $dlDate->format('Y');
            $yearStart = new \DateTimeImmutable("$year-01-01");
            $yearEnd   = new \DateTimeImmutable("$year-12-31 23:59:59");

            $count = (int) $em->createQueryBuilder()
                ->select('COUNT(vg.id)')
                ->from(Vignette::class, 'vg')
                ->join('vg.depense', 'd')
                ->where('d.voiture = :voiture')
                ->andWhere('vg.deletedAt IS NULL')
                ->andWhere('d.deletedAt IS NULL')
                ->andWhere('d.dateFin >= :yearStart')
                ->andWhere('d.dateFin <= :yearEnd')
                ->setParameter('voiture', $voiture)
                ->setParameter('yearStart', $yearStart)
                ->setParameter('yearEnd', $yearEnd)
                ->getQuery()
                ->getSingleScalarResult();

            if ($count > 0) {
                // Direct add: soft-delete the existing vignette for this year so the new one can replace it
                $existing = $em->createQueryBuilder()
                    ->select('vg')
                    ->from(Vignette::class, 'vg')
                    ->join('vg.depense', 'd')
                    ->where('d.voiture = :voiture')
                    ->andWhere('vg.deletedAt IS NULL')
                    ->andWhere('d.deletedAt IS NULL')
                    ->andWhere('d.dateFin >= :yearStart')
                    ->andWhere('d.dateFin <= :yearEnd')
                    ->setParameter('voiture', $voiture)
                    ->setParameter('yearStart', $yearStart)
                    ->setParameter('yearEnd', $yearEnd)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($existing) {
                    $existing->setDeletedAt(new \DateTimeImmutable());
                    $em->persist($existing);
                }
            }
        }

        $depense = null;
        if (!empty($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
        }
        if (!$depense && $voiture) {
            $depense = new Depense();
            $depense->setDateDebut(new \DateTimeImmutable('today'));
            $depense->setDateFin(new \DateTimeImmutable($data['dateLimite']));
            if (!empty($data['dateFacture'])) $depense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
            $depense->setTypeDepense('vignette');
            $depense->setMontant((string) ($data['montant'] ?? 0));
            $depense->setMontantPaye('0');
            $depense->setStatut(StatusEnum::IMPAYE);
            $depense->setVoiture($voiture);
            if ($voiture->getBureau()) $depense->setBureau($voiture->getBureau());
            $depense->setCreeAu(new \DateTimeImmutable());
            $depense->setCreePar($this->getUser());
            $em->persist($depense);
            $em->flush();
        }

        $vignette = new Vignette();
        if ($depense) $vignette->setDepense($depense);
        $vignette->setAnnee((int) ($data['annee'] ?? date('Y')));
        $vignette->setCreeAu(new \DateTimeImmutable());
        $vignette->setCreePar($this->getUser());
        $em->persist($vignette);
        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($depense && $montantPaye > 0) {
            $this->paymentService->createInitialPayment($depense, $montantPaye, $em, $this->getUser());
        }

        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        $this->activityLog->logCreate('Vignette', $vignette->getId(), $this->snapshotVignette($vignette), $voiture?->getBureau());

        return $this->json(['message' => 'Vignette créée', 'id' => $vignette->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Vignette $vignette, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vignette);
        $data    = json_decode($request->getContent(), true);
        $oldSnap = $this->snapshotVignette($vignette);

        if (isset($data['annee']))      $vignette->setAnnee((int) $data['annee']);
        if (isset($data['dateLimite']) && $vignette->getDepense()) $vignette->getDepense()->setDateFin(new \DateTimeImmutable($data['dateLimite']));
        if (array_key_exists('filePath', $data)) $vignette->setFilePath($data['filePath']);
        if (array_key_exists('dateFacture', $data) && $vignette->getDepense()) {
            $vignette->getDepense()->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        $vignette->setEditAu(new \DateTimeImmutable());

        if (isset($data['montant']) && $vignette->getDepense()) {
            $vignette->getDepense()->setMontant((string) $data['montant']);
            $this->paymentService->recalculate($vignette->getDepense()->getId(), $em);
        }

        $em->flush();

        $voitureSync = $vignette->getDepense()?->getVoiture();
        $this->activityLog->logUpdate('Vignette', $vignette->getId(), $oldSnap, $this->snapshotVignette($vignette), $voitureSync?->getBureau());
        if ($voitureSync) $this->flm->applyEvent($voitureSync, new TemporalSyncTriggered($voitureSync->getId(), $voitureSync->getBureau()?->getId()));

        return $this->json(['message' => 'Vignette mise à jour']);
    }

    #[Route('/{id}/renew', name: 'renew', methods: ['POST'])]
    public function renew(Vignette $vignette, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vignette);
        if ($vignette->getDeletedAt() !== null) {
            return $this->json(['error' => 'Vignette supprimée.'], 404);
        }

        $data    = json_decode($request->getContent(), true);
        $oldDep  = $vignette->getDepense();
        $voiture = $oldDep?->getVoiture();

        // Soft-delete old vignette so it stays in DB as history
        $vignette->setDeletedAt(new \DateTimeImmutable());
        if ($oldDep) $oldDep->setDeletedAt(new \DateTimeImmutable());
        $em->persist($vignette);

        // New depense
        $newDepense = new Depense();
        $newDepense->setDateDebut(new \DateTimeImmutable('today'));
        $newDepense->setDateFin(new \DateTimeImmutable($data['dateLimite']));
        if (!empty($data['dateFacture'])) $newDepense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
        $newDepense->setTypeDepense('vignette');
        $newDepense->setMontant((string) ($data['montant'] ?? 0));
        $newDepense->setMontantPaye('0');
        $newDepense->setStatut(\App\Enum\StatusEnum::IMPAYE);
        $newDepense->setVoiture($voiture);
        if ($voiture?->getBureau()) $newDepense->setBureau($voiture->getBureau());
        $newDepense->setCreeAu(new \DateTimeImmutable());
        $newDepense->setCreePar($this->getUser());
        $em->persist($newDepense);

        // New vignette
        $newVignette = new Vignette();
        $newVignette->setDepense($newDepense);
        $newVignette->setAnnee((int) ($data['annee'] ?? (int) date('Y')));
        $newVignette->setCreeAu(new \DateTimeImmutable());
        $newVignette->setCreePar($this->getUser());
        $em->persist($newVignette);

        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($montantPaye > 0) {
            $this->paymentService->createInitialPayment($newDepense, $montantPaye, $em, $this->getUser());
        }

        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        $this->activityLog->logCreate('Vignette', $newVignette->getId(), $this->snapshotVignette($newVignette), $voiture?->getBureau());

        return $this->json([
            'message'      => 'Renouvellement effectué.',
            'newId'        => $newVignette->getId(),
            'newDepenseId' => $newDepense->getId(),
        ], 201);
    }

    #[Route('/{id}/file', name: 'upload_file', methods: ['POST'])]
    public function uploadFile(Vignette $vignette, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vignette);
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier'], 400);
        }

        $ext      = $file->guessExtension() ?? 'bin';
        $filename = uniqid('vig_') . '.' . $ext;
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/compliance/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $file->move($uploadDir, $filename);
        $vignette->setFilePath('/uploads/compliance/' . $filename);
        $em->flush();

        return $this->json(['filePath' => $vignette->getFilePath()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Vignette $vignette, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vignette);
        $voiture = $vignette->getDepense()?->getVoiture();
        $snap    = $this->snapshotVignette($vignette);
        $id      = $vignette->getId();
        $vignette->setDeletedAt(new \DateTimeImmutable());
        if ($vignette->getDepense()) {
            $vignette->getDepense()->setDeletedAt(new \DateTimeImmutable());
        }
        $em->flush();

        $this->activityLog->logDelete('Vignette', $id, $snap, $voiture?->getBureau());
        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Vignette supprimée'], 200);
    }
}
