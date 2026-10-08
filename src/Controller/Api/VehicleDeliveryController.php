<?php

namespace App\Controller\Api;

use App\Entity\Damage;
use App\Entity\VehicleDelivery;
use App\Fleet\Event\RentalStarted;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\ReservationLifecycleManager;
use App\Repository\ContratRepository;
use App\Repository\ReservationRepository;
use App\Repository\VehicleDeliveryRepository;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vehicle-delivery', name: 'app_api_vd_')]
#[IsGranted('ROLE_USER')]
class VehicleDeliveryController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    /** Bureau-locked staff/managers may only touch deliveries belonging to their own
     *  bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(VehicleDelivery $delivery): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($delivery->getReservation()?->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Livraison introuvable');
        }
    }

    private function assertReservationBureauAccess(\App\Entity\Reservation $reservation): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($reservation->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Réservation introuvable');
        }
    }

    private function serialize(VehicleDelivery $d): array
    {
        $res  = $d->getReservation();
        $voit = $res?->getVoiture();
        $cli  = $res?->getClient();

        return [
            'id'                         => $d->getId(),
            'reservationId'              => $res?->getId(),
            'voiture'                    => $voit ? trim($voit->getMarque() . ' ' . $voit->getModele()) : null,
            'immatriculation'            => $voit?->getImmatriculation(),
            'clientNom'                  => $cli?->getNom(),
            'fuelLevelOut'               => $d->getFuelLevelOut(),
            'mileageOut'                 => $d->getMileageOut(),
            'hasExtincteur'              => $d->isHasExtincteur(),
            'hasLavage'                  => $d->isHasLavage(),
            'hasPlaqueDepannage'         => $d->isHasPlaqueDepannage(),
            'hasCric'                    => $d->isHasCric(),
            'hasGilet'                   => $d->isHasGilet(),
            'hasRoueSecours'             => $d->isHasRoueSecours(),
            'hasSiegeBebe'               => $d->isHasSiegeBebe(),
            'hasTriangle'                => $d->isHasTriangle(),
            'equipementNotes'            => $d->getEquipementNotes(),
            'deliveryNotes'              => $d->getDeliveryNotes(),
            'signatureClientDepart'      => $d->getSignatureClientDepart(),
            'signatureDeuxiemeChauffeur' => $d->getSignatureDeuxiemeChauffeur(),
            'signatureSocieteDepart'     => $d->getSignatureSocieteDepart(),
            'damages'                    => array_map(fn($dmg) => [
                'id'          => $dmg->getId(),
                'zone'        => $dmg->getZone(),
                'type'        => $dmg->getSeverity(),
                'description' => $dmg->getDescription(),
                'x'           => $dmg->getX(),
                'y'           => $dmg->getY(),
            ], $d->getDamages()->toArray()),
            'creeAu' => $d->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu' => $d->getEditAu()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $page     = $this->getPageParam($request);

        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(VehicleDelivery::class, 'd')
            ->join('d.reservation', 'r')
            ->join('r.voiture', 'v')
            ->orderBy('d.creeAu', 'DESC');

        if ($bureauId) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }

        [$items, $total] = $this->paginateQb($qb, $page);

        return $this->json([
            'data' => array_map(fn($d) => $this->serialize($d), $items),
            'meta' => $this->paginateMeta($total, $page),
        ]);
    }

    #[Route('/contrat/{contratId}', name: 'by_contrat', methods: ['GET'], requirements: ['contratId' => '\d+'])]
    public function getByContrat(
        int $contratId,
        ContratRepository $contratRepo,
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
        $this->assertReservationBureauAccess($reservation);

        $delivery = $deliveryRepo->findOneBy(['reservation' => $reservation]);

        return $this->json($delivery ? $this->serialize($delivery) : null);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(VehicleDelivery $delivery): JsonResponse
    {
        $this->assertBureauAccess($delivery);
        return $this->json($this->serialize($delivery));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo,
        VehicleDeliveryRepository $deliveryRepo,
        FleetLifecycleManager $flm,
        ReservationLifecycleManager $reservationLifecycle
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['reservationId'])) {
            return $this->json(['error' => 'reservationId is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reservation = $reservationRepo->find((int) $data['reservationId']);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], Response::HTTP_NOT_FOUND);
        }
        $this->assertReservationBureauAccess($reservation);

        $terminalStatuses = ['terminee', 'annulee', 'annule', 'cancelled'];
        if (in_array($reservation->getReservationStatus(), $terminalStatuses, true)) {
            return $this->json(
                ['error' => 'Impossible de créer une livraison pour une réservation terminée ou annulée.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // If a delivery already exists, update it instead of creating a duplicate.
        $existing = $deliveryRepo->findOneBy(['reservation' => $reservation]);
        if ($existing) {
            $this->applyData($existing, $data, $em);
            $existing->setEditAu(new \DateTimeImmutable());
            $em->flush();
            return $this->json($this->serialize($existing), Response::HTTP_OK);
        }

        $delivery = new VehicleDelivery();
        $delivery->setReservation($reservation);
        $this->applyData($delivery, $data, $em);

        $em->persist($delivery);
        $em->flush();

        // Only advance status when it's still at 'confirmed' (or the French
        // spelling the dossier's "Confirmer" button writes). Routed through
        // ReservationLifecycleManager + FleetLifecycleManager::applyEvent() —
        // this endpoint used to write reservationStatus directly and never
        // touch Voiture.voitureStatus at all, silently leaving the vehicle's
        // lifecycle state stale (never resynced to "rented"), unlike
        // LocationController::remettreLesCles.
        $voiture = $reservation->getVoiture();
        if (in_array($reservation->getReservationStatus(), ['confirmed', 'confirmee', 'pending'], true)) {
            $reservationLifecycle->transition($reservation, 'en_cours');
            if ($voiture !== null) {
                $flm->applyEvent($voiture, new RentalStarted(
                    $voiture->getId(),
                    $reservation->getId(),
                    $delivery->getId(),
                    (int) ($delivery->getMileageOut() ?? $voiture->getKilometrageActuel() ?? 0),
                    $delivery->getFuelLevelOut() ?? 'vide',
                ));
            }
        }

        return $this->json($this->serialize($delivery), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(VehicleDelivery $delivery, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($delivery);
        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyData($delivery, $data, $em);
        $delivery->setEditAu(new \DateTimeImmutable());

        // Guard against a blank/0 submission silently erasing the vehicle's real odometer
        // reading (e.g. a form whose mileage field was never filled in), and against an
        // accidental lower value overwriting a higher, already-correct one — an odometer
        // only ever goes up.
        if (isset($data['mileageOut']) && $data['mileageOut'] !== null) {
            $newMileage = (int) $data['mileageOut'];
            $voiture = $delivery->getReservation()?->getVoiture();
            if ($voiture !== null && $newMileage > 0 && $newMileage >= (int) ($voiture->getKilometrageActuel() ?? 0)) {
                $voiture->setKilometrageActuel($newMileage);
            }
        }

        $em->flush();

        return $this->json($this->serialize($delivery));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(VehicleDelivery $delivery, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($delivery);
        $em->remove($delivery);
        $em->flush();

        return $this->json(['message' => 'Livraison supprimée']);
    }

    private const FUEL_LEVELS = ['vide', 'quart', 'moitie', 'trois_quarts', 'plein'];

    private function applyData(VehicleDelivery $d, array $data, EntityManagerInterface $em): void
    {
        $has = fn(string $k) => array_key_exists($k, $data);
        $val = fn(string $k, mixed $def = null) => $data[$k] ?? $def;

        if ($has('fuelLevelOut')) {
            $level = $val('fuelLevelOut');
            if ($level !== null && !in_array($level, self::FUEL_LEVELS, true)) {
                $level = 'vide';
            }
            $d->setFuelLevelOut($level);
        }
        if ($has('mileageOut'))                 $d->setMileageOut($val('mileageOut') !== null ? (int) $val('mileageOut') : null);
        if ($has('hasExtincteur'))              $d->setHasExtincteur((bool) $val('hasExtincteur'));
        if ($has('hasLavage'))                  $d->setHasLavage((bool) $val('hasLavage'));
        if ($has('hasPlaqueDepannage'))         $d->setHasPlaqueDepannage((bool) $val('hasPlaqueDepannage'));
        if ($has('hasCric'))                    $d->setHasCric((bool) $val('hasCric'));
        if ($has('hasGilet'))                   $d->setHasGilet((bool) $val('hasGilet'));
        if ($has('hasRoueSecours'))             $d->setHasRoueSecours((bool) $val('hasRoueSecours'));
        if ($has('hasSiegeBebe'))               $d->setHasSiegeBebe((bool) $val('hasSiegeBebe'));
        if ($has('hasTriangle'))                $d->setHasTriangle((bool) $val('hasTriangle'));
        if ($has('equipementNotes'))            $d->setEquipementNotes($val('equipementNotes'));
        if ($has('deliveryNotes'))              $d->setDeliveryNotes($val('deliveryNotes'));
        if ($has('signatureClientDepart'))      $d->setSignatureClientDepart($val('signatureClientDepart'));
        if ($has('signatureDeuxiemeChauffeur')) $d->setSignatureDeuxiemeChauffeur($val('signatureDeuxiemeChauffeur'));
        if ($has('signatureSocieteDepart'))     $d->setSignatureSocieteDepart($val('signatureSocieteDepart'));

        if ($has('damages') && is_array($data['damages'])) {
            foreach ($d->getDamages()->toArray() as $existing) {
                $d->removeDamage($existing);
                $em->remove($existing);
            }

            foreach ($data['damages'] as $dmgData) {
                $dmg = new Damage();
                $dmg->setZone($dmgData['zone'] ?? 'AVANT');
                $dmg->setDescription($dmgData['description'] ?? null);
                $dmg->setSeverity($dmgData['type'] ?? $dmgData['severity'] ?? 'scratch');
                $dmg->setX(isset($dmgData['x']) ? (float) $dmgData['x'] : null);
                $dmg->setY(isset($dmgData['y']) ? (float) $dmgData['y'] : null);
                $d->addDamage($dmg);
                $em->persist($dmg);
            }
        }
    }
}
