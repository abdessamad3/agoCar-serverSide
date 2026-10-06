<?php

namespace App\Controller\Api;

use App\Entity\Vente;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\VehicleSold;
use App\Fleet\Exception\LifecycleViolationException;
use App\Repository\AchatVoitureRepository;
use App\Repository\VenteRepository;
use App\Repository\VoitureRepository;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vente', name: 'app_api_vente_')]
class VenteController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private FleetLifecycleManager $flm,
    ) {}

    /** Bureau-locked staff/managers may only touch sales belonging to their own
     *  bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(Vente $vente): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($vente->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Vente introuvable');
        }
    }

    private function serialize(Vente $v): array
    {
        $voit = $v->getVoiture();
        return [
            'id'             => $v->getId(),
            'dateVente'      => $v->getDateVente()->format('Y-m-d'),
            'prixVente'      => (float) $v->getPrixVente(),
            'acheteur'       => $v->getAcheteur(),
            'benefice'       => $v->getBenefice() !== null ? (float) $v->getBenefice() : null,
            'notes'          => $v->getNotes(),
            'voitureId'      => $voit->getId(),
            'voiture'        => trim(($voit->getMarque() ?? '') . ' ' . ($voit->getModele() ?? '')),
            'marque'         => $voit->getMarque(),
            'modele'         => $voit->getModele(),
            'annee'          => $voit->getAnnee(),
            'immatriculation'=> $voit->getImmatriculation(),
            'couleur'        => $voit->getCouleur(),
            'prixAchat'      => $voit->getPrixAchat() ? (float) $voit->getPrixAchat() : null,
            'bureauId'       => $v->getBureau()?->getId(),
            'bureau'         => $v->getBureau()?->getNom(),
            'creePar'        => $v->getCreePar()?->getId(),
            'creeAu'         => $v->getCreeAu()->format('Y-m-d H:i:s'),
            'editAu'         => $v->getEditAu()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('v')
            ->from(\App\Entity\Vente::class, 'v')
            ->where('v.deletedAt IS NULL')
            ->orderBy('v.creeAu', 'DESC');

        if ($bureauId) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        [$items, $total] = $this->paginateQb($qb, $page);
        return $this->json(['data' => array_map(fn($v) => $this->serialize($v), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Vente $vente): JsonResponse
    {
        $this->assertBureauAccess($vente);
        return $this->json($this->serialize($vente));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['voitureId']) || empty($data['dateVente']) || empty($data['prixVente'])) {
            return $this->json(['error' => 'voitureId, dateVente et prixVente sont requis.'], 422);
        }

        $voiture = $voitureRepo->find((int) $data['voitureId']);
        if (!$voiture) {
            return $this->json(['error' => 'Voiture introuvable.'], 404);
        }

        $prixVente = (float) $data['prixVente'];

        try {
            $this->flm->assertCanCompleteSale($voiture, $prixVente);
        } catch (LifecycleViolationException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();

        $prixAchat = $voiture->getPrixAchat();
        $benefice  = $prixAchat !== null ? $prixVente - (float) $prixAchat : 0.0;

        $vente = new Vente();
        $vente->setVoiture($voiture);
        $vente->setDateVente(new \DateTimeImmutable($data['dateVente']));
        $vente->setPrixVente((string) $prixVente);
        $vente->setAcheteur($data['acheteur'] ?? null);
        $vente->setNotes($data['notes'] ?? null);
        $vente->setBenefice((string) $benefice);
        $vente->setCreeAu(new \DateTimeImmutable());
        $vente->setCreePar($user);

        if ($user->getBureau()) {
            $vente->setBureau($user->getBureau());
        }

        // Flush vente first to obtain its ID, then hand status change to FLM
        $em->persist($vente);
        $em->flush();

        $this->flm->applyEvent($voiture, new VehicleSold(
            $voiture->getId(),
            $vente->getId(),
            new \DateTimeImmutable($data['dateVente']),
            $prixVente,
            $data['acheteur'] ?? '',
            $benefice,
        ));

        return $this->json(['message' => 'Vente enregistrée.', 'id' => $vente->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Vente $vente, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vente);
        $data = json_decode($request->getContent(), true);

        if (isset($data['dateVente']))  $vente->setDateVente(new \DateTimeImmutable($data['dateVente']));
        if (isset($data['prixVente']))  $vente->setPrixVente((string) $data['prixVente']);
        if (array_key_exists('acheteur', $data)) $vente->setAcheteur($data['acheteur'] ?: null);
        if (array_key_exists('notes', $data))    $vente->setNotes($data['notes'] ?: null);
        if (array_key_exists('benefice', $data)) $vente->setBenefice(isset($data['benefice']) ? (string) $data['benefice'] : null);
        $vente->setEditAu(new \DateTimeImmutable());

        $em->flush();
        return $this->json(['message' => 'Vente mise à jour.']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Vente $vente, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($vente);
        $vente->setDeletedAt(new \DateTimeImmutable());
        $em->flush();
        return $this->json(['message' => 'Vente supprimée.']);
    }
}
