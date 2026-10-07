<?php

namespace App\Controller\Api;

use App\Entity\TarifSaisonnier;
use App\Repository\BureauRepository;
use App\Repository\TarifSaisonnierRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tarif-saisonnier', name: 'app_api_tarif_saisonnier_')]
class TarifSaisonnierController extends AbstractController
{
    use PaginationTrait;

    /** Create/update/delete directly change pricing strategy — restrict to admins and
     *  managers. List/show stay open (ROLE_USER, via the global access_control rule) so
     *  any staff member can see why a total looks the way it does. */
    private function assertCanManage(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Seuls les administrateurs et gestionnaires peuvent modifier les tarifs saisonniers.');
        }
    }

    private function serialize(TarifSaisonnier $t): array
    {
        return [
            'id'             => $t->getId(),
            'libelle'        => $t->getLibelle(),
            'dateDebut'      => $t->getDateDebut()?->format('Y-m-d'),
            'dateFin'        => $t->getDateFin()?->format('Y-m-d'),
            'typeAjustement' => $t->getTypeAjustement(),
            'valeur'         => $t->getValeur() !== null ? (float) $t->getValeur() : null,
            'bureauId'       => $t->getBureau()?->getId(),
            'bureauNom'      => $t->getBureau()?->getNom(),
            'actif'          => $t->isActif(),
            'creeAu'         => $t->getCreeAu()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(TarifSaisonnierRepository $repo, Request $request): JsonResponse
    {
        $page = $this->getPageParam($request);
        $qb   = $repo->createQueryBuilder('t')
            ->where('t.deletedAt IS NULL')
            ->orderBy('t.dateDebut', 'DESC');
        [$items, $total] = $this->paginateQb($qb, $page);
        return $this->json(['data' => array_map(fn($t) => $this->serialize($t), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(TarifSaisonnier $tarifSaisonnier): JsonResponse
    {
        return $this->json($this->serialize($tarifSaisonnier));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, BureauRepository $bureauRepo): JsonResponse
    {
        $this->assertCanManage();
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['libelle']) || empty($data['dateDebut']) || empty($data['dateFin']) || !isset($data['valeur'])) {
            return $this->json(['error' => 'libelle, dateDebut, dateFin et valeur sont requis'], 422);
        }

        $dateDebut = new \DateTimeImmutable($data['dateDebut']);
        $dateFin   = new \DateTimeImmutable($data['dateFin']);
        if ($dateFin < $dateDebut) {
            return $this->json(['error' => 'La date de fin doit être après la date de début'], 422);
        }

        $type = $data['typeAjustement'] ?? TarifSaisonnier::TYPE_PERCENTAGE;
        if (!in_array($type, [TarifSaisonnier::TYPE_PERCENTAGE, TarifSaisonnier::TYPE_FIXED], true)) {
            return $this->json(['error' => 'typeAjustement doit être "percentage" ou "fixed"'], 422);
        }

        $tarif = new TarifSaisonnier();
        $tarif->setLibelle($data['libelle']);
        $tarif->setDateDebut($dateDebut);
        $tarif->setDateFin($dateFin);
        $tarif->setTypeAjustement($type);
        $tarif->setValeur(number_format((float) $data['valeur'], 2, '.', ''));
        $tarif->setActif($data['actif'] ?? true);
        if (!empty($data['bureauId'])) {
            $tarif->setBureau($bureauRepo->find((int) $data['bureauId']));
        }
        $tarif->setCreeAu(new \DateTimeImmutable());

        $em->persist($tarif);
        $em->flush();

        return $this->json(['message' => 'Tarif saisonnier créé', 'id' => $tarif->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(TarifSaisonnier $tarifSaisonnier, Request $request, EntityManagerInterface $em, BureauRepository $bureauRepo): JsonResponse
    {
        $this->assertCanManage();
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['libelle'])) $tarifSaisonnier->setLibelle($data['libelle']);

        $dateDebut = isset($data['dateDebut']) ? new \DateTimeImmutable($data['dateDebut']) : $tarifSaisonnier->getDateDebut();
        $dateFin   = isset($data['dateFin'])   ? new \DateTimeImmutable($data['dateFin'])   : $tarifSaisonnier->getDateFin();
        if ($dateFin < $dateDebut) {
            return $this->json(['error' => 'La date de fin doit être après la date de début'], 422);
        }
        if (isset($data['dateDebut'])) $tarifSaisonnier->setDateDebut($dateDebut);
        if (isset($data['dateFin']))   $tarifSaisonnier->setDateFin($dateFin);

        if (isset($data['typeAjustement'])) {
            if (!in_array($data['typeAjustement'], [TarifSaisonnier::TYPE_PERCENTAGE, TarifSaisonnier::TYPE_FIXED], true)) {
                return $this->json(['error' => 'typeAjustement doit être "percentage" ou "fixed"'], 422);
            }
            $tarifSaisonnier->setTypeAjustement($data['typeAjustement']);
        }
        if (isset($data['valeur'])) $tarifSaisonnier->setValeur(number_format((float) $data['valeur'], 2, '.', ''));
        if (isset($data['actif']))  $tarifSaisonnier->setActif((bool) $data['actif']);
        if (array_key_exists('bureauId', $data)) {
            $tarifSaisonnier->setBureau(!empty($data['bureauId']) ? $bureauRepo->find((int) $data['bureauId']) : null);
        }

        $em->flush();

        return $this->json(['message' => 'Tarif saisonnier mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(TarifSaisonnier $tarifSaisonnier, EntityManagerInterface $em): JsonResponse
    {
        $this->assertCanManage();
        $tarifSaisonnier->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['message' => 'Tarif saisonnier supprimé'], 200);
    }
}
