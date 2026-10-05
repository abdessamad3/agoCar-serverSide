<?php

namespace App\Controller\Api;

use App\Entity\Reparation;
use App\Entity\Depense;
use App\Enum\StatusEnum;
use App\Repository\ReparationRepository;
use App\Repository\DepenseRepository;
use App\Repository\PaiementDepenseRepository;
use App\Repository\VoitureRepository;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\TemporalSyncTriggered;
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

#[Route('/api/reparation', name: 'app_api_reparation_')]
#[IsGranted('ROLE_USER')]
class ReparationController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementDepenseRepository $paieRepo,
        private DepensePaymentService     $paymentService,
        private VoitureStatusService      $statusService,
        private FleetLifecycleManager     $flm,
    ) {}

    private function serialize(Reparation $r): array
    {
        $dep  = $r->getDepense();
        $voit = $dep?->getVoiture();
        return [
            'id'                   => $r->getId(),
            'descriptionTechnique' => $r->getDescriptionTechnique(),
            'depenseId'            => $dep?->getId(),
            'voitureId'            => $voit?->getId(),
            'voiture'              => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'montant'              => $dep?->getMontant(),
            'date'                 => $dep?->getDateDebut()?->format('Y-m-d'),
            'dateDebut'            => $dep?->getDateDebut()?->format('Y-m-d'),
            'dateFin'              => $dep?->getDateFin()?->format('Y-m-d'),
            'statut'               => $dep?->getStatut()?->value,
            'montantPaye'          => $dep?->getMontantPaye() !== null ? (float) $dep->getMontantPaye() : 0.0,
            'montantTotal'         => $dep?->getMontant() ? (float) $dep->getMontant() : 0.0,
            'reste'                => DepensePaymentService::computeReste((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paymentStatut'        => DepensePaymentService::paymentStatutLabel((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paiementCount'        => $dep ? $this->paieRepo->countByDepense($dep->getId()) : 0,
            'creeAu'               => $r->getCreeAu()?->format('Y-m-d'),
            'dateFacture'          => $dep?->getDateFacture()?->format('Y-m-d'),
            'filePaths'            => $r->getFilePaths(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ReparationRepository $repo, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);
        $search   = trim((string) $request->query->get('search', ''));

        $qb = $em->createQueryBuilder()
            ->select('r')
            ->from(Reparation::class, 'r')
            ->join('r.depense', 'd')
            ->join('d.voiture', 'v')
            ->orderBy('r.creeAu', 'DESC');

        if ($bureauId !== null) {
            $qb->where('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        $voitureId = (int) $request->query->get('voitureId', 0);
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }

        if ($search) {
            $qb->andWhere('r.descriptionTechnique LIKE :s OR v.immatriculation LIKE :s OR v.marque LIKE :s')
               ->setParameter('s', "%$search%");
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map(fn($r) => $this->serialize($r), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Reparation $reparation): JsonResponse
    {
        return $this->json($this->serialize($reparation));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DepenseRepository $depenseRepo,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $depense = null;
        if (!empty($data['depenseId'])) {
            $depense = $depenseRepo->find($data['depenseId']);
        }
        if (!$depense) {
            $depense = new Depense();
            $depense->setDateDebut(new \DateTimeImmutable($data['dateDebut'] ?? 'now'));
            if (!empty($data['dateFin'])) $depense->setDateFin(new \DateTimeImmutable($data['dateFin']));
            if (!empty($data['dateFacture'])) $depense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
            $depense->setTypeDepense('reparation');
            $depense->setMontant((string) ($data['montant'] ?? 0));
            $depense->setMontantPaye('0');
            $depense->setStatut(StatusEnum::IMPAYE);
            $depense->setDescription($data['descriptionTechnique'] ?? null);
            $depense->setCreeAu(new \DateTimeImmutable());
            $depense->setCreePar($this->getUser());
            if (!empty($data['voitureId'])) {
                $voiture = $voitureRepo->find($data['voitureId']);
                if ($voiture) {
                    $depense->setVoiture($voiture);
                    if ($voiture->getBureau()) $depense->setBureau($voiture->getBureau());
                }
            }
            $em->persist($depense);
            $em->flush();
        }

        $reparation = new Reparation();
        $reparation->setDepense($depense);
        $reparation->setDescriptionTechnique($data['descriptionTechnique'] ?? '');
        if (isset($data['filePaths'])) $reparation->setFilePaths((array) $data['filePaths']);
        $reparation->setCreeAu(new \DateTimeImmutable());
        $reparation->setCreePar($this->getUser());
        $em->persist($reparation);
        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($montantPaye > 0) {
            $this->paymentService->createInitialPayment($depense, $montantPaye, $em, $this->getUser());
        }

        if ($depense->getVoiture()) {
            $v = $depense->getVoiture();
            $this->flm->applyEvent($v, new TemporalSyncTriggered($v->getId(), $v->getBureau()?->getId()));
        }

        return $this->json(['message' => 'Réparation créée', 'id' => $reparation->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Reparation $reparation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['descriptionTechnique'])) $reparation->setDescriptionTechnique($data['descriptionTechnique']);
        if (array_key_exists('filePaths', $data)) $reparation->setFilePaths((array) ($data['filePaths'] ?? []));
        if (isset($data['dateDebut']) && $reparation->getDepense()) $reparation->getDepense()->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (array_key_exists('dateFin', $data) && $reparation->getDepense()) $reparation->getDepense()->setDateFin(!empty($data['dateFin']) ? new \DateTimeImmutable($data['dateFin']) : null);
        if (array_key_exists('dateFacture', $data) && $reparation->getDepense()) {
            $reparation->getDepense()->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        $reparation->setEditAu(new \DateTimeImmutable());

        if (isset($data['montant']) && $reparation->getDepense()) {
            $reparation->getDepense()->setMontant((string) $data['montant']);
            $this->paymentService->recalculate($reparation->getDepense()->getId(), $em);
        }

        $em->flush();

        $voiture = $reparation->getDepense()?->getVoiture();
        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));
        }

        return $this->json(['message' => 'Réparation mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Reparation $reparation, EntityManagerInterface $em): JsonResponse
    {
        $voiture = $reparation->getDepense()?->getVoiture();
        $reparation->setDeletedAt(new \DateTimeImmutable());
        if ($reparation->getDepense()) {
            $reparation->getDepense()->setDeletedAt(new \DateTimeImmutable());
        }
        $em->flush();

        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));
        }

        return $this->json(['message' => 'Réparation supprimée'], 200);
    }
}
