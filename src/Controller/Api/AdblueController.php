<?php

namespace App\Controller\Api;

use App\Entity\Adblue;
use App\Entity\Depense;
use App\Enum\StatusEnum;
use App\Repository\AdblueRepository;
use App\Repository\DepenseRepository;
use App\Repository\PaiementDepenseRepository;
use App\Repository\VoitureRepository;
use App\Service\DepensePaymentService;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/adblue', name: 'app_api_adblue_')]
class AdblueController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementDepenseRepository $paieRepo,
        private DepensePaymentService     $paymentService
    ) {}

    private function serialize(Adblue $a): array
    {
        $dep  = $a->getDepense();
        $voit = $dep?->getVoiture();
        return [
            'id'             => $a->getId(),
            'quantite'       => $a->getQuantiteLitre(),
            'quantiteLitre'  => $a->getQuantiteLitre(),
            'quantiteLitres' => $a->getQuantiteLitre(),
            'depenseId'      => $dep?->getId(),
            'voitureId'      => $voit?->getId(),
            'voiture'        => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'date'           => $dep?->getDateDebut()?->format('Y-m-d'),
            'montant'        => $dep?->getMontant(),
            'cout'           => $dep?->getMontant(),
            'montantTotal'   => $dep?->getMontant() ? (float) $dep->getMontant() : 0.0,
            'montantPaye'    => $dep?->getMontantPaye() ? (float) $dep->getMontantPaye() : 0.0,
            'reste'          => DepensePaymentService::computeReste((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paymentStatut'  => DepensePaymentService::paymentStatutLabel((float)($dep?->getMontant() ?? 0), (float)($dep?->getMontantPaye() ?? 0)),
            'paiementCount'  => $dep ? $this->paieRepo->countByDepense($dep->getId()) : 0,
            'creeAu'         => $a->getCreeAu()?->format('Y-m-d'),
            'dateFacture'    => $dep?->getDateFacture()?->format('Y-m-d'),
            'filePaths'      => $a->getFilePaths(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('ab')
            ->from(Adblue::class, 'ab')
            ->join('ab.depense', 'd')
            ->join('d.voiture', 'v')
            ->orderBy('ab.creeAu', 'DESC');

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
    public function show(Adblue $adblue): JsonResponse
    {
        return $this->json($this->serialize($adblue));
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
                $depense->setTypeDepense('adblue');
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

        $adblue = new Adblue();
        if ($depense) $adblue->setDepense($depense);
        $adblue->setQuantiteLitre($data['quantite'] ?? $data['quantiteLitre'] ?? 0);
        if (isset($data['filePaths'])) $adblue->setFilePaths((array) $data['filePaths']);
        $adblue->setCreeAu(new \DateTimeImmutable());
        $adblue->setCreePar($this->getUser());
        $em->persist($adblue);
        $em->flush();

        $montantPaye = (float) ($data['montantPaye'] ?? 0);
        if ($depense && $montantPaye > 0) {
            $this->paymentService->createInitialPayment($depense, $montantPaye, $em, $this->getUser());
        }

        return $this->json(['message' => 'AdBlue créé', 'id' => $adblue->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Adblue $adblue, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $quantite = $data['quantite'] ?? $data['quantiteLitre'] ?? null;
        if ($quantite !== null) $adblue->setQuantiteLitre($quantite);
        if (array_key_exists('filePaths', $data)) $adblue->setFilePaths((array) ($data['filePaths'] ?? []));
        $adblue->setEditAu(new \DateTimeImmutable());

        if (isset($data['date']) && $adblue->getDepense()) {
            $adblue->getDepense()->setDateDebut(new \DateTimeImmutable($data['date']));
        }
        if (array_key_exists('dateFacture', $data) && $adblue->getDepense()) {
            $adblue->getDepense()->setDateFacture(!empty($data['dateFacture']) ? new \DateTimeImmutable($data['dateFacture']) : null);
        }
        if (isset($data['montant']) && $adblue->getDepense()) {
            $adblue->getDepense()->setMontant((string) $data['montant']);
            $this->paymentService->recalculate($adblue->getDepense()->getId(), $em);
        }

        $em->flush();
        return $this->json(['message' => 'AdBlue mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Adblue $adblue, EntityManagerInterface $em): JsonResponse
    {
        $adblue->setDeletedAt(new \DateTimeImmutable());
        if ($adblue->getDepense()) {
            $adblue->getDepense()->setDeletedAt(new \DateTimeImmutable());
        }
        $em->flush();
        return $this->json(['message' => 'AdBlue supprimé'], 200);
    }
}
