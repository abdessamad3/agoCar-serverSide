<?php

namespace App\Controller\Api;

use App\Entity\Damage;
use App\Entity\Depense;
use App\Entity\VehicleReturnInspection;
use App\Enum\StatusEnum;
use App\Repository\ContratRepository;
use App\Repository\ReservationRepository;
use App\Repository\VehicleDeliveryRepository;
use App\Repository\VehicleReturnInspectionRepository;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicle-return-inspection', name: 'app_api_vri_')]
class VehicleReturnInspectionController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    private function serialize(VehicleReturnInspection $i, ?int $kmDepart = null): array
    {
        $res  = $i->getReservation();
        $voit = $res->getVoiture();
        $cli  = $res->getClient();

        $kmRetour   = $i->getKilometrage();
        $kmEffectue = ($kmDepart !== null && $kmRetour !== null) ? max(0, $kmRetour - $kmDepart) : null;

        $deliveryDate = null;
        $joursFactures = null;
        if ($i->getInspectedAt() && $res->getDateDebut()) {
            $deliveryDate  = $res->getDateDebut();
            $diffSeconds   = $i->getInspectedAt()->getTimestamp() - $deliveryDate->getTimestamp();
            $joursFactures = (int) ceil($diffSeconds / 86400);

            // Extra day if return is more than 2 hours past planned return
            if ($res->getDateFin()) {
                $overdue = $i->getInspectedAt()->getTimestamp() - $res->getDateFin()->getTimestamp();
                if ($overdue > 7200) {
                    ++$joursFactures;
                }
            }
        }

        return [
            'id'                     => $i->getId(),
            'reservationId'          => $res->getId(),
            'voitureId'              => $voit?->getId(),
            'voiture'                => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'immatriculation'        => $voit?->getImmatriculation(),
            'clientNom'              => $cli?->getNom(),
            'fuelLevelIn'            => $i->getFuelLevelIn(),
            'kilometrage'            => $kmRetour,
            'kmDepart'               => $kmDepart,
            'kmEffectue'             => $kmEffectue,
            'joursFactures'          => $joursFactures,
            'photos'                 => $i->getPhotos() ?? [],
            'notes'                  => $i->getNotes(),
            'condition'              => $i->getCondition(),
            'fuelCharge'             => $i->getFuelCharge() !== null ? (float) $i->getFuelCharge() : null,
            'lateCharge'             => $i->getLateCharge() !== null ? (float) $i->getLateCharge() : null,
            'damageCharge'           => $i->getDamageCharge() !== null ? (float) $i->getDamageCharge() : null,
            'totalAdditionalCharges' => $i->getTotalAdditionalCharges(),
            'signatureClientRetour'  => $i->getSignatureClientRetour(),
            'signatureSocieteRetour' => $i->getSignatureSocieteRetour(),
            'damageItems'            => array_map(fn($dmg) => [
                'id'          => $dmg->getId(),
                'zone'        => $dmg->getZone(),
                'type'        => $dmg->getSeverity(),
                'description' => $dmg->getDescription(),
                'x'           => $dmg->getX(),
                'y'           => $dmg->getY(),
            ], $i->getDamageItems()->toArray()),
            'inspectedBy'            => $i->getInspectedBy()?->getId(),
            'inspectedAt'            => $i->getInspectedAt()->format('Y-m-d H:i:s'),
            'editAu'                 => $i->getEditAu()?->format('Y-m-d H:i:s'),
            'dateFin'                => $res->getDateFin()?->format('Y-m-d'),
            'reservationStatus'      => $res->getReservationStatus(),
        ];
    }

    private function getKmDepartForReservation(int $reservationId, VehicleDeliveryRepository $deliveryRepo): ?int
    {
        $delivery = $deliveryRepo->findOneBy(['reservation' => $reservationId]);
        return $delivery?->getMileageOut();
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        Request $request,
        EntityManagerInterface $em,
        VehicleDeliveryRepository $deliveryRepo
    ): JsonResponse {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('i')
            ->from(VehicleReturnInspection::class, 'i')
            ->join('i.reservation', 'r')
            ->join('r.voiture', 'v')
            ->orderBy('i.inspectedAt', 'DESC');

        if ($bureauId) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        [$items, $total] = $this->paginateQb($qb, $page);

        return $this->json([
            'data' => array_map(
                fn($i) => $this->serialize($i, $this->getKmDepartForReservation($i->getReservation()->getId(), $deliveryRepo)),
                $items
            ),
            'meta' => $this->paginateMeta($total, $page),
        ]);
    }

    #[Route('/contrat/{contratId}', name: 'by_contrat', methods: ['GET'], requirements: ['contratId' => '\d+'])]
    public function getByContrat(
        int $contratId,
        ContratRepository $contratRepo,
        VehicleReturnInspectionRepository $inspectionRepo,
        VehicleDeliveryRepository $deliveryRepo
    ): JsonResponse {
        $contrat = $contratRepo->find($contratId);
        if (!$contrat) {
            return $this->json(['error' => 'Contrat introuvable'], Response::HTTP_NOT_FOUND);
        }

        $reservation = $contrat->getReservation();
        if (!$reservation) {
            return $this->json(null, Response::HTTP_OK);
        }

        $inspection = $inspectionRepo->findOneBy(['reservation' => $reservation]);
        if (!$inspection) {
            return $this->json(null, Response::HTTP_OK);
        }

        $kmDepart = $this->getKmDepartForReservation($reservation->getId(), $deliveryRepo);

        return $this->json($this->serialize($inspection, $kmDepart));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(VehicleReturnInspection $inspection, VehicleDeliveryRepository $deliveryRepo): JsonResponse
    {
        $kmDepart = $this->getKmDepartForReservation($inspection->getReservation()->getId(), $deliveryRepo);

        return $this->json($this->serialize($inspection, $kmDepart));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['reservationId'])) {
            return $this->json(['error' => 'reservationId est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reservation = $reservationRepo->find((int) $data['reservationId']);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($reservation->getReservationStatus() !== 'en_cours') {
            return $this->json(
                ['error' => "L'inspection de retour ne peut être créée que pour un contrat actif (en_cours)."],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $inspection = new VehicleReturnInspection();
        $inspection->setReservation($reservation);
        $inspection->setInspectedBy($this->getUser());
        $inspection->setInspectedAt(new \DateTimeImmutable());

        $this->applyData($inspection, $data, $em);

        $em->persist($inspection);
        $em->flush();

        return $this->json($this->serialize($inspection), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(
        VehicleReturnInspection $inspection,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyData($inspection, $data, $em);
        $inspection->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json($this->serialize($inspection));
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validate(
        VehicleReturnInspection $inspection,
        EntityManagerInterface $em,
        VehicleDeliveryRepository $deliveryRepo
    ): JsonResponse {
        $reservation = $inspection->getReservation();

        if ($reservation->getReservationStatus() === 'terminee') {
            return $this->json(['error' => 'Ce contrat est déjà clôturé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Close the contract
        $reservation->setReservationStatus('terminee');

        // Update vehicle mileage with return km
        $voiture = $reservation->getVoiture();
        if ($inspection->getKilometrage() !== null && $voiture !== null) {
            $voiture->setKilometrageActuel($inspection->getKilometrage());
        }

        // Auto-create Depense for damages
        $damageCharge = (float) ($inspection->getDamageCharge() ?? 0);
        if ($damageCharge > 0 && $voiture !== null) {
            /** @var \App\Entity\Utilisateur $user */
            $user   = $this->getUser();
            $bureau = $user?->getBureau();

            $depense = new Depense();
            $depense->setDate(new \DateTimeImmutable());
            $depense->setTypeDepense('reparation');
            $depense->setDescription('Frais de dommages — retour contrat');
            $depense->setMontant((string) $damageCharge);
            $depense->setStatut(StatusEnum::EN_ATTENTE);
            $depense->setVoiture($voiture);
            $depense->setBureau($bureau);
            $depense->setCreePar($user);
            $depense->setCreeAu(new \DateTimeImmutable());

            $em->persist($depense);
        }

        $inspection->setEditAu(new \DateTimeImmutable());
        $em->flush();

        $kmDepart = $this->getKmDepartForReservation($reservation->getId(), $deliveryRepo);

        return $this->json($this->serialize($inspection, $kmDepart));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(VehicleReturnInspection $inspection, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($inspection);
        $em->flush();

        return $this->json(['message' => 'Inspection supprimée.']);
    }

    private const FUEL_LEVELS = ['vide', 'quart', 'moitie', 'trois_quarts', 'plein'];

    private function applyData(VehicleReturnInspection $i, array $data, EntityManagerInterface $em): void
    {
        $has = fn(string $k) => array_key_exists($k, $data);
        $val = fn(string $k, mixed $def = null) => $data[$k] ?? $def;

        if ($has('fuelLevelIn')) {
            $level = $val('fuelLevelIn');
            if ($level !== null && !in_array($level, self::FUEL_LEVELS, true)) {
                $level = 'vide';
            }
            $i->setFuelLevelIn($level);
        }
        if ($has('kilometrage'))            $i->setKilometrage($val('kilometrage') !== null ? (int) $val('kilometrage') : null);
        if ($has('photos'))                 $i->setPhotos($val('photos'));
        if ($has('notes'))                  $i->setNotes($val('notes'));
        if ($has('condition'))              $i->setCondition($val('condition', 'clean'));
        if ($has('fuelCharge'))             $i->setFuelCharge($val('fuelCharge') !== null ? (string) (float) $val('fuelCharge') : null);
        if ($has('lateCharge'))             $i->setLateCharge($val('lateCharge') !== null ? (string) (float) $val('lateCharge') : null);
        if ($has('damageCharge'))           $i->setDamageCharge($val('damageCharge') !== null ? (string) (float) $val('damageCharge') : null);
        if ($has('signatureClientRetour'))  $i->setSignatureClientRetour($val('signatureClientRetour'));
        if ($has('signatureSocieteRetour')) $i->setSignatureSocieteRetour($val('signatureSocieteRetour'));

        if ($has('damageItems') && is_array($data['damageItems'])) {
            foreach ($i->getDamageItems()->toArray() as $existing) {
                $i->removeDamageItem($existing);
                $em->remove($existing);
            }

            foreach ($data['damageItems'] as $dmgData) {
                $dmg = new Damage();
                $dmg->setZone($dmgData['zone'] ?? 'AVANT');
                $dmg->setDescription($dmgData['description'] ?? null);
                $dmg->setSeverity($dmgData['type'] ?? $dmgData['severity'] ?? 'scratch');
                $dmg->setX(isset($dmgData['x']) ? (float) $dmgData['x'] : null);
                $dmg->setY(isset($dmgData['y']) ? (float) $dmgData['y'] : null);
                $i->addDamageItem($dmg);
                $em->persist($dmg);
            }
        }
    }
}
