<?php

namespace App\Controller\Api;

use App\Entity\Vidange;
use App\Entity\Depense;
use App\Enum\StatusEnum;
use App\Repository\VidangeRepository;
use App\Repository\DepenseRepository;
use App\Repository\PaiementDepenseRepository;
use App\Repository\VoitureRepository;
use App\Service\DepensePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vidange', name: 'app_api_vidange_')]
#[IsGranted('ROLE_USER')]
class VidangeController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementDepenseRepository $paieRepo,
        private DepensePaymentService     $paymentService
    ) {}

    /** Bureau-locked staff/managers may only touch oil-change records belonging to
     *  their own bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(Vidange $vidange): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($vidange->getDepense()?->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Vidange introuvable');
        }
    }

    private function serialize(Vidange $v): array
    {
        $dep  = $v->getDepense();
        $voit = $dep?->getVoiture();
        return [
            'id'                 => $v->getId(),
            'kilometrageSuivant' => $v->getKilometrageSuivant(),
            'kilometrage'        => $v->getKilometrageSuivant(),
            'intervalleKm'       => $v->getIntervalleKm() ?? 10000,
            'filtreAir'          => $v->isFiltreAir(),
            'filtreHuile'        => $v->isFiltreHuile(),
            'filtreCarburant'    => $v->isFiltreCarburant(),
            'depenseId'          => $dep?->getId(),
            'voitureId'          => $voit?->getId(),
            'voiture'            => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'date'               => $dep?->getDateDebut()?->format('Y-m-d'),
            'montant'            => $dep?->getMontant(),
            'cout'               => $dep?->getMontant(),
            'montantTotal'       => $dep?->getMontant() ? (float) $dep->getMontant() : 0.0,
            'montantPaye'        => $dep?->getMontantPaye() ? (float) $dep->getMontantPaye() : 0.0,
            'reste'              => DepensePaymentService::computeReste((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paymentStatut'      => DepensePaymentService::paymentStatutLabel((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paiementCount'      => $dep ? $this->paieRepo->countByDepense($dep->getId()) : 0,
            'filePath'           => $v->getFilePath(),
            'filePaths'          => $v->getFilePaths() ?: ($v->getFilePath() ? [$v->getFilePath()] : []),
            'creeAu'             => $v->getCreeAu()?->format('Y-m-d'),
            'dateFacture'        => $dep?->getDateFacture()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('vi')
            ->from(Vidange::class, 'vi')
            ->join('vi.depense', 'd')
            ->join('d.voiture', 'v')
            ->orderBy('vi.creeAu', 'DESC');

        if ($bureauId !== null) {
            $qb->where('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        $voitureId = (int) $request->query->get('voitureId', 0);
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }

        [$items, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map(fn($v) => $this->serialize($v), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Vidange $vidange): JsonResponse
    {
        $this->assertBureauAccess($vidange);
        return $this->json($this->serialize($vidange));
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
        if (!$depense && !empty($data['voitureId'])) {
            $voiture = $voitureRepo->find((int) $data['voitureId']);
            if ($voiture) {
                $depense = new Depense();
                $depense->setDateDebut(!empty($data['date']) ? new \DateTimeImmutable($data['date']) : new \DateTimeImmutable());
                if (!empty($data['dateFacture'])) $depense->setDateFacture(new \DateTimeImmutable($data['dateFacture']));
                $depense->setTypeDepense('vidange');
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
        }

        $vidange = new Vidange();
        if ($depense) $vidange->setDepense($depense);
        $vidange->setKilometrageSuivant($data['kilometrage'] ?? $data['kilometrageSuivant'] ?? 0);
        $vidange->setIntervalleKm((int) ($data['intervalleKm'] ?? 10000));
        $vidange->setFiltreAir($data['filtreAir'] ?? false);
        $vidange->setFiltreHuile($data['filtreHuile'] ?? false);
        $vidange->setFiltreCarburant($data['filtreCarburant'] ?? false);
        if (isset($data['filePath'])) $vidange->setFilePath($data['filePath']);
        if (isset($data['filePaths'])) $vidange->setFilePaths((array) $data['filePaths']);
        $vidange->setCreeAu(new \DateTimeImmutable());
        $vidange->setCreePar($this->getUser());
        $em->persist($vidange);
        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($depense && $montantPaye > 0) {
            $this->paymentService->createInitialPayment($depense, $montantPaye, $em, $this->getUser());
        }

        return $this->json(['message' => 'Vidange créée', 'id' => $vidange->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Vidange $vidange, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vidange);
        $data = json_decode($request->getContent(), true);

        $km = $data['kilometrage'] ?? $data['kilometrageSuivant'] ?? null;
        if ($km !== null)                        $vidange->setKilometrageSuivant($km);
        if (isset($data['intervalleKm']))        $vidange->setIntervalleKm((int) $data['intervalleKm']);
        if (isset($data['filtreAir']))           $vidange->setFiltreAir($data['filtreAir']);
        if (isset($data['filtreHuile']))     $vidange->setFiltreHuile($data['filtreHuile']);
        if (isset($data['filtreCarburant'])) $vidange->setFiltreCarburant($data['filtreCarburant']);
        if (array_key_exists('filePath', $data)) $vidange->setFilePath($data['filePath']);
        if (array_key_exists('filePaths', $data)) $vidange->setFilePaths((array) ($data['filePaths'] ?? []));
        $vidange->setEditAu(new \DateTimeImmutable());

        if (isset($data['date']) && $vidange->getDepense()) {
            $vidange->getDepense()->setDateDebut(new \DateTimeImmutable($data['date']));
        }
        if (array_key_exists('dateFacture', $data) && $vidange->getDepense()) {
            $vidange->getDepense()->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        if (isset($data['montant']) && $vidange->getDepense()) {
            $vidange->getDepense()->setMontant((string) $data['montant']);
            $this->paymentService->recalculate($vidange->getDepense()->getId(), $em);
        }

        $em->flush();
        return $this->json(['message' => 'Vidange mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Vidange $vidange, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vidange);
        $vidange->setDeletedAt(new \DateTimeImmutable());
        if ($vidange->getDepense()) {
            $vidange->getDepense()->setDeletedAt(new \DateTimeImmutable());
        }
        $em->flush();
        return $this->json(['message' => 'Vidange supprimée'], 200);
    }
}
