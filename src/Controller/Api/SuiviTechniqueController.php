<?php

namespace App\Controller\Api;

use App\Entity\Depense;
use App\Entity\SuiviTechnique;
use App\Enum\StatusEnum;
use App\Repository\PaiementDepenseRepository;
use App\Repository\SuiviTechniqueRepository;
use App\Repository\VoitureRepository;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Service\ComplianceService;
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

#[Route('/api/suivi-technique', name: 'app_api_suivi_technique_')]
#[IsGranted('ROLE_USER')]
class SuiviTechniqueController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementDepenseRepository $paieRepo,
        private DepensePaymentService     $paymentService,
        private VoitureStatusService      $statusService,
        private ComplianceService         $complianceService,
        private SuiviTechniqueRepository  $suiviRepo,
        private FleetLifecycleManager     $flm,
    ) {}

    private function serialize(SuiviTechnique $s): array
    {
        $dep  = $s->getDepense();
        $voit = $s->getVoiture();
        return [
            'id'             => $s->getId(),
            'dateReglages'   => $dep?->getDateDebut()?->format('Y-m-d'),
            'date'           => $dep?->getDateDebut()?->format('Y-m-d'),
            'dateFin'        => $dep?->getDateFin()?->format('Y-m-d'),
            'voitureId'      => $voit?->getId(),
            'voiture'        => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'depenseId'      => $dep?->getId(),
            'montantTotal'   => $dep?->getMontant() ? (float) $dep->getMontant() : null,
            'montantPaye'    => $dep?->getMontantPaye() ? (float) $dep->getMontantPaye() : 0.0,
            'reste'          => DepensePaymentService::computeReste((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paymentStatut'  => DepensePaymentService::paymentStatutLabel((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paiementCount'  => $dep ? $this->paieRepo->countByDepense($dep->getId()) : 0,
            'creeAu'         => $s->getCreeAu()?->format('Y-m-d'),
            'dateFacture'    => $dep?->getDateFacture()?->format('Y-m-d'),
            'filePath'       => $s->getFilePath(),
            'resultat'       => $s->getResultat(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('s')
            ->from(SuiviTechnique::class, 's')
            ->join('s.voiture', 'v')
            ->orderBy('s.creeAu', 'DESC');

        if ($bureauId !== null) {
            $qb->where('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        $voitureId = (int) $request->query->get('voitureId', 0);
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map(fn($s) => $this->serialize($s), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(SuiviTechnique $suivi): JsonResponse
    {
        return $this->json($this->serialize($suivi));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $voiture = null;
        if (!empty($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
        }

        if ($voiture && !$this->complianceService->isVisiteRequired($voiture)) {
            return $this->json([
                'error'   => 'visite_not_required',
                'message' => 'Ce véhicule a moins de 3 ans — la visite technique n\'est pas encore obligatoire.',
            ], 422);
        }

        $debut = new \DateTimeImmutable($data['dateReglages'] ?? 'now');
        if ($voiture && !empty($data['dateFin'])) {
            $fin = new \DateTimeImmutable($data['dateFin']);
            if ($this->suiviRepo->hasOverlap($voiture, $debut, $fin)) {
                return $this->json(['error' => 'Une visite technique active chevauche déjà cette période.'], 422);
            }
        }

        $depense = new Depense();
        $depense->setDateDebut($debut);
        if (!empty($data['dateFin'])) $depense->setDateFin(new \DateTimeImmutable($data['dateFin']));
        if (!empty($data['dateFacture'])) $depense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
        $depense->setTypeDepense('suivi_technique');
        $depense->setMontant((string) ($data['montantTotal'] ?? '0'));
        $depense->setMontantPaye('0');
        $depense->setStatut(StatusEnum::IMPAYE);
        $depense->setVoiture($voiture);
        if ($voiture?->getBureau()) $depense->setBureau($voiture->getBureau());
        $depense->setCreeAu(new \DateTimeImmutable());
        $depense->setCreePar($this->getUser());
        $em->persist($depense);
        $em->flush();

        $suivi = new SuiviTechnique();
        $suivi->setDepense($depense);
        if ($voiture) $suivi->setVoiture($voiture);
        $suivi->setResultat($data['resultat'] ?? null);
        $suivi->setCreeAu(new \DateTimeImmutable());
        $suivi->setCreePar($this->getUser());
        $em->persist($suivi);
        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($montantPaye > 0) {
            $this->paymentService->createInitialPayment($depense, $montantPaye, $em, $this->getUser());
        }

        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Suivi technique créé', 'id' => $suivi->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(SuiviTechnique $suivi, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['dateReglages']) && $suivi->getDepense()) $suivi->getDepense()->setDateDebut(new \DateTimeImmutable($data['dateReglages']));
        if (array_key_exists('dateFin', $data) && $suivi->getDepense()) $suivi->getDepense()->setDateFin(!empty($data['dateFin']) ? new \DateTimeImmutable($data['dateFin']) : null);
        if (array_key_exists('dateFacture', $data) && $suivi->getDepense()) {
            $suivi->getDepense()->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        if (array_key_exists('filePath', $data)) $suivi->setFilePath($data['filePath'] ?: null);
        if (array_key_exists('resultat', $data)) $suivi->setResultat($data['resultat'] ?: null);
        $suivi->setEditAu(new \DateTimeImmutable());

        if (isset($data['montantTotal']) && $suivi->getDepense()) {
            $suivi->getDepense()->setMontant((string) $data['montantTotal']);
            $this->paymentService->recalculate($suivi->getDepense()->getId(), $em);
        }

        $em->flush();

        $voiture = $suivi->getVoiture();
        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Suivi technique mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(SuiviTechnique $suivi, EntityManagerInterface $em): JsonResponse
    {
        $voiture = $suivi->getVoiture();
        $suivi->setDeletedAt(new \DateTimeImmutable());
        if ($suivi->getDepense()) {
            $suivi->getDepense()->setDeletedAt(new \DateTimeImmutable());
        }
        $em->flush();

        if ($voiture) $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json(['message' => 'Suivi technique supprimé'], 200);
    }
}
