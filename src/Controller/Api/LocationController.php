<?php

namespace App\Controller\Api;

use App\Entity\Contrat;
use App\Entity\Damage;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\VehicleDelivery;
use App\Entity\VehicleReturnInspection;
use App\Enum\StatusEnum;
use App\Fleet\Event\RentalStarted;
use App\Fleet\Event\ReturnInspectionCompleted;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\ReservationLifecycleManager;
use App\Repository\ContratRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\VehicleDeliveryRepository;
use App\Repository\VehicleReturnInspectionRepository;
use App\Service\ActivityLogService;
use App\Service\ComplianceService;
use App\Service\PaymentSumHelper;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/location', name: 'app_api_location_')]
class LocationController extends AbstractController
{
    use BureauAwareTrait;

    public function __construct(
        private ComplianceService $compliance,
        private FleetLifecycleManager $flm,
        private ActivityLogService $activityLog,
        private ReservationLifecycleManager $reservationLifecycle,
    ) {}

    /** Bureau-locked staff/managers may only touch reservations belonging to their own
     *  bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(Reservation $reservation): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        $resBureauId = $reservation->getVoiture()?->getBureau()?->getId();
        if ($resBureauId !== $bureauId) {
            throw $this->createNotFoundException('Réservation introuvable');
        }
    }

    // ── GET /api/location/{id}/full ─────────────────────────────────────────

    #[Route('/{reservationId}/full', name: 'full', methods: ['GET'], requirements: ['reservationId' => '\d+'])]
    public function full(
        int $reservationId,
        ReservationRepository $reservationRepo,
        ContratRepository $contratRepo,
        VehicleDeliveryRepository $deliveryRepo,
        VehicleReturnInspectionRepository $returnRepo,
        PaiementRepository $paiementRepo
    ): JsonResponse {
        $reservation = $reservationRepo->find($reservationId);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], Response::HTTP_NOT_FOUND);
        }
        $this->assertBureauAccess($reservation);

        $voiture    = $reservation->getVoiture();
        $client     = $reservation->getClient();
        $dc         = $reservation->getDeuxiemeChauffeur();
        $contrat    = $contratRepo->findOneBy(['reservation' => $reservation]);
        $delivery   = $deliveryRepo->findOneBy(['reservation' => $reservation]);
        $inspection = $returnRepo->findOneBy(['reservation' => $reservation]);
        $paiements  = $paiementRepo->findBy(
            ['reservation' => $reservation],
            ['datePaiement' => 'DESC']
        );

        $compliance = $voiture ? $this->compliance->getComplianceStatus($voiture) : null;

        $total       = (float) ($reservation->getTotal() ?? 0);
        $montantPaye = (float) ($reservation->getMontantPaye() ?? 0);

        $timestamps = array_filter([
            $reservation->getEditAu(),
            $contrat?->getEditAu(),
            $delivery?->getEditAu(),
            $inspection?->getEditAu(),
        ]);
        $updatedAt = $timestamps ? max($timestamps) : null;

        return $this->json([
            'id'             => $reservation->getId(),
            'status'         => $reservation->getReservationStatus(),
            'updatedAt'      => ($updatedAt instanceof \DateTimeImmutable)
                ? $updatedAt->format('Y-m-d H:i:s')
                : $reservation->getCreeAu()?->format('Y-m-d H:i:s'),
            'montantTotal'   => $total,
            'montantPaye'    => $montantPaye,
            'montantRestant' => $reservation->getMontantRestant(),
            'montantSurpaye' => $reservation->getMontantSurpaye(),
            'balance'        => $reservation->getBalance(),
            'paymentStatus'  => $reservation->getPaymentStatus(),
            'reservation'    => $this->serializeReservation($reservation, $voiture, $client, $dc, $compliance),
            'contrat'        => $contrat    ? $this->serializeContrat($contrat)                                       : null,
            'vehicleDelivery'          => $delivery   ? $this->serializeDelivery($delivery)                           : null,
            'vehicleReturnInspection'  => $inspection ? $this->serializeInspection($inspection, $delivery?->getMileageOut()) : null,
            'paiements'      => array_map(fn($p) => [
                'id'           => $p->getId(),
                'montant'      => (float) $p->getMontant(),
                'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
                'modePaiement' => $p->getModePaiement(),
                'note'         => $p->getNote(),
                'creeAu'       => $p->getCreeAu()?->format('Y-m-d H:i:s'),
            ], $paiements),
            'timeline' => $this->buildTimeline($reservation, $contrat, $delivery, $inspection),
        ]);
    }

    // ── POST /api/location/{id}/remettre-les-cles ───────────────────────────

    #[Route('/{reservationId}/remettre-les-cles', name: 'remettre_cles', methods: ['POST'], requirements: ['reservationId' => '\d+'])]
    public function remettreLesCles(
        int $reservationId,
        Request $request,
        ReservationRepository $reservationRepo,
        ContratRepository $contratRepo,
        VehicleDeliveryRepository $deliveryRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $reservation = $reservationRepo->find($reservationId);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }
        $this->assertBureauAccess($reservation);

        $status = $reservation->getReservationStatus();
        if (!in_array($status, ['confirmed', 'confirmee', 'pending'], true)) {
            return $this->json(['error' => 'La réservation n\'est pas dans l\'état requis pour la livraison'], 400);
        }

        $voiture = $reservation->getVoiture();
        if ($voiture) {
            $comp = $this->compliance->getComplianceStatus($voiture);
            if (in_array($comp['overall'] ?? '', ['EXPIRED', 'UNKNOWN'], true)) {
                return $this->json([
                    'error'   => 'compliance_blocked',
                    'message' => 'Ce véhicule a une conformité expirée ou inconnue. Livraison bloquée.',
                ], 400);
            }
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // ── Contrat (create or update) ──────────────────────────────────────
        $contrat = $contratRepo->findOneBy(['reservation' => $reservation]);
        if (!$contrat) {
            $contrat = new Contrat();
            $contrat->setReservation($reservation);
        }

        $contrat->setHasCaution((bool) ($data['hasCaution'] ?? false));
        $contrat->setCautionMontant(isset($data['cautionMontant']) && $data['cautionMontant'] !== '' && $data['cautionMontant'] !== null
            ? number_format((float) $data['cautionMontant'], 2, '.', '') : null);
        $contrat->setFranchise(isset($data['franchise']) && $data['franchise'] !== '' && $data['franchise'] !== null
            ? number_format((float) $data['franchise'], 2, '.', '') : null);
        $contrat->setSignedAt(new \DateTimeImmutable());
        $contrat->setPrixParJourSnapshot($reservation->getPrixParJour());

        if (!empty($data['faitA'])) {
            $contrat->setFaitA($data['faitA']);
        } elseif (!$contrat->getFaitA()) {
            $contrat->setFaitA($voiture?->getBureau()?->getNom() ?? null);
        }

        // nbJoursFactures: use override if provided, else auto-compute from dates
        if (!empty($data['nbJoursFactures'])) {
            $contrat->setNbJoursFactures((int) $data['nbJoursFactures']);
        } else {
            $debut = $reservation->getDateDebut();
            $fin   = $reservation->getDateFin();
            if ($debut && $fin) {
                $diffSecs = $fin->getTimestamp() - $debut->getTimestamp();
                $contrat->setNbJoursFactures(max(1, (int) ceil($diffSecs / 86400)));
            }
        }

        $em->persist($contrat);

        // ── VehicleDelivery (create or update) ─────────────────────────────
        $delivery = $deliveryRepo->findOneBy(['reservation' => $reservation]);
        if (!$delivery) {
            $delivery = new VehicleDelivery();
            $delivery->setReservation($reservation);
        }

        $delivery->setFuelLevelOut($data['fuelLevelOut'] ?? 'vide');
        $mileageOut = isset($data['mileageOut']) && $data['mileageOut'] !== null
            ? (int) $data['mileageOut']
            : ($voiture?->getKilometrageActuel() ?? null);
        $delivery->setMileageOut($mileageOut);
        $delivery->setHasExtincteur((bool) ($data['hasExtincteur'] ?? false));
        $delivery->setHasLavage((bool) ($data['hasLavage'] ?? false));
        $delivery->setHasPlaqueDepannage((bool) ($data['hasPlaqueDepannage'] ?? false));
        $delivery->setHasCric((bool) ($data['hasCric'] ?? false));
        $delivery->setHasGilet((bool) ($data['hasGilet'] ?? false));
        $delivery->setHasRoueSecours((bool) ($data['hasRoueSecours'] ?? false));
        $delivery->setHasSiegeBebe((bool) ($data['hasSiegeBebe'] ?? false));
        $delivery->setHasTriangle((bool) ($data['hasTriangle'] ?? false));
        $delivery->setSignatureClientDepart($data['signatureClientDepart'] ?? null);
        $delivery->setSignatureSocieteDepart($data['signatureSocieteDepart'] ?? null);
        $delivery->setSignatureDeuxiemeChauffeur($data['signatureDeuxiemeChauffeur'] ?? null);
        $delivery->setEquipementNotes($data['equipementNotes'] ?? null);
        $delivery->setDeliveryNotes($data['deliveryNotes'] ?? null);

        if (isset($delivery) && $delivery->getEditAu() !== null) {
            $delivery->setEditAu(new \DateTimeImmutable());
        }

        $em->persist($delivery);

        // ── Create Damage records for each reported damaged part at hand-over ──
        if (!empty($data['damagedParts']) && is_array($data['damagedParts'])) {
            foreach ($data['damagedParts'] as $partId) {
                if (!is_string($partId) || strlen($partId) > 30) {
                    continue;
                }
                $damage = new Damage();
                $damage->setVehicleDelivery($delivery);
                $damage->setZone($partId);
                $damage->setSeverity('scratch');
                $damage->setStatus('open');
                $em->persist($damage);
            }
        }

        // ── Update lieu on Reservation if provided ──────────────────────────
        if (!empty($data['lieuLivraison'])) {
            $reservation->setLieuLivraison($data['lieuLivraison']);
        }
        if (!empty($data['lieuRetour'])) {
            $reservation->setLieuRetour($data['lieuRetour']);
        }

        // ── Transition status ───────────────────────────────────────────────
        $this->reservationLifecycle->transition($reservation, 'en_cours');

        // Voiture.voitureStatus may only be written via FleetLifecycleManager::applyEvent()
        // (enforced by VoitureStatusWriteGuard) — it recomputes the correct lifecycle state
        // from the now-active reservation instead of a raw direct write.
        if ($voiture) {
            $this->flm->applyEvent($voiture, new RentalStarted(
                $voiture->getId(),
                $reservation->getId(),
                $delivery->getId(),
                (int) ($mileageOut ?? 0),
                $delivery->getFuelLevelOut(),
            ));
        }

        return $this->json(['success' => true, 'message' => 'Véhicule remis. Contrat actif.']);
    }

    // ── POST /api/location/{id}/cloture ─────────────────────────────────────

    #[Route('/{reservationId}/cloture', name: 'cloture', methods: ['POST'], requirements: ['reservationId' => '\d+'])]
    public function cloture(
        int $reservationId,
        Request $request,
        ReservationRepository $reservationRepo,
        ContratRepository $contratRepo,
        VehicleReturnInspectionRepository $returnRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $reservation = $reservationRepo->find($reservationId);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }
        $this->assertBureauAccess($reservation);

        if ($reservation->getReservationStatus() !== 'en_cours') {
            return $this->json(['error' => 'La réservation n\'est pas en cours'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['fuelLevelIn'])) {
            return $this->json(['error' => 'Le niveau de carburant au retour est requis'], 400);
        }
        if (empty($data['kilometrage'])) {
            return $this->json(['error' => 'Le kilométrage retour est requis'], 400);
        }

        // ── Build VehicleReturnInspection ───────────────────────────────────
        $inspection = $returnRepo->findOneBy(['reservation' => $reservation]);
        if (!$inspection) {
            $inspection = new VehicleReturnInspection();
            $inspection->setReservation($reservation);
        }

        $inspectedAt = !empty($data['inspectedAt'])
            ? new \DateTimeImmutable($data['inspectedAt'])
            : new \DateTimeImmutable();

        $inspection->setFuelLevelIn($data['fuelLevelIn']);
        $inspection->setKilometrage((int) $data['kilometrage']);
        $inspection->setInspectedAt($inspectedAt);
        $inspection->setCondition($data['condition'] ?? 'clean');
        $inspection->setFuelCharge(isset($data['fuelCharge'])    ? number_format((float) $data['fuelCharge'],    2, '.', '') : null);
        $inspection->setLateCharge(isset($data['lateCharge'])    ? number_format((float) $data['lateCharge'],    2, '.', '') : null);
        $inspection->setDamageCharge(isset($data['damageCharge']) ? number_format((float) $data['damageCharge'], 2, '.', '') : null);
        $inspection->setEquipmentCharge(isset($data['equipmentCharge']) ? number_format((float) $data['equipmentCharge'], 2, '.', '') : null);
        $inspection->setNotes($data['notes'] ?? null);
        $inspection->setSignatureClientRetour($data['signatureClientRetour'] ?? null);
        $inspection->setSignatureSocieteRetour($data['signatureSocieteRetour'] ?? null);

        $remiseMontant = (float) ($data['remiseMontant'] ?? 0);
        $inspection->setRemiseMontant($remiseMontant != 0 ? number_format($remiseMontant, 2, '.', '') : null);
        $inspection->setRemiseMotif($data['remiseMotif'] ?? null);
        $inspection->setEditAu(new \DateTimeImmutable());

        // Compute cautionRemboursee
        $contrat = $contratRepo->findOneBy(['reservation' => $reservation]);
        $cautionMontant = $contrat ? (float) ($contrat->getCautionMontant() ?? 0) : 0;
        $totalCharges   = $inspection->getTotalAdditionalCharges();
        $cautionRemboursee = max(0.0, $cautionMontant - $totalCharges + $remiseMontant);
        $inspection->setCautionRemboursee(number_format($cautionRemboursee, 2, '.', ''));

        // Fold return-time charges (fuel/late/damage/equipment) and any remise into the
        // reservation's actual total, now, once -- montantRestant/paymentStatus/balance are
        // all derived strictly from total vs montantPaye (by design, so they can't drift), but
        // nothing previously updated total here, so every other screen (client debt, reports,
        // the reservations list) silently missed these charges; only this dossier's own Retour
        // tab recomputed them for display. cloture() can only run once per reservation (guarded
        // above by the en_cours precondition), so this can't double-apply on a re-close.
        if ($totalCharges > 0 || $remiseMontant > 0) {
            $netAdjustment = $totalCharges - $remiseMontant;
            $reservation->setTotal(number_format((float) $reservation->getTotal() + $netAdjustment, 2, '.', ''));
        }

        $em->persist($inspection);

        // ── Create Damage records for each reported damaged part ────────────
        if (!empty($data['damagedParts']) && is_array($data['damagedParts']) && $inspection->getCondition() !== 'clean') {
            $severity = $inspection->getCondition() === 'major_damage' ? 'dent' : 'scratch';
            foreach ($data['damagedParts'] as $partId) {
                if (!is_string($partId) || strlen($partId) > 30) {
                    continue;
                }
                $damage = new Damage();
                $damage->setReturnInspection($inspection);
                $damage->setZone($partId);
                $damage->setSeverity($severity);
                $damage->setStatus('open');
                $em->persist($damage);
            }
        }

        // ── Transition status ───────────────────────────────────────────────
        $this->reservationLifecycle->transition($reservation, 'terminee');

        // ── Optional final payment, collected at the moment the car is returned —
        // a normal Paiement row (shows up in the Paiements tab/history/debt totals exactly
        // like any other payment), not a special "closing payment" type. Soft/optional: staff
        // can close with 0 and let the client pay later, same philosophy as everywhere else.
        $closingAmount = (float) ($data['closingPaymentAmount'] ?? 0);
        if ($closingAmount > 0) {
            $paiement = new Paiement();
            $paiement->setMontant((string) $closingAmount);
            $paiement->setDatePaiement(new \DateTimeImmutable());
            $paiement->setStatut(StatusEnum::PAYEE);
            $paiement->setModePaiement($data['closingPaymentMode'] ?? null);
            $paiement->setReservation($reservation);
            $paiement->setCreeAu(new \DateTimeImmutable());
            $paiement->setCreePar($this->getUser());
            $em->persist($paiement);
            $em->flush();

            $newTotalPaid = PaymentSumHelper::sumActivePayments($em, Paiement::class, 'reservation', $reservation);
            $reservation->setMontantPaye((string) $newTotalPaid);
            $em->flush();

            $this->activityLog->logCreate('Paiement', $paiement->getId(), [
                'montant'       => $paiement->getMontant(),
                'modePaiement'  => $paiement->getModePaiement(),
                'reservationId' => $reservation->getId(),
                'context'       => 'closing',
            ], $reservation->getBureau());
        }

        // Voiture.voitureStatus may only be written via FleetLifecycleManager::applyEvent()
        // (enforced by VoitureStatusWriteGuard). The ReturnInspectionCompleted post-effect also
        // syncs voiture.kilometrageActuel automatically — no need to set it here separately.
        $voiture = $reservation->getVoiture();
        if ($voiture) {
            $this->flm->applyEvent($voiture, new ReturnInspectionCompleted(
                $voiture->getId(),
                $reservation->getId(),
                $inspection->getId(),
                (int) $data['kilometrage'],
                $data['fuelLevelIn'],
                (float) ($data['fuelCharge'] ?? 0),
                (float) ($data['lateCharge'] ?? 0),
                (float) ($data['damageCharge'] ?? 0),
                (float) ($data['equipmentCharge'] ?? 0),
                $data['condition'] ?? 'clean',
            ));
        }

        return $this->json(['success' => true, 'message' => 'Contrat clôturé. Véhicule disponible.']);
    }

    // ── POST /api/location/{id}/annuler-cloture ─────────────────────────────

    /** Reopens a closed contract — only within 1h of the original closure (checked against the
     *  return inspection's editAu, the server timestamp set at closing time). Reverses the
     *  reservation status and deletes the return inspection, then re-syncs the vehicle's
     *  lifecycle state. Deliberately does NOT touch any payment collected at closing — money
     *  already received shouldn't silently un-record itself; staff remove it explicitly via the
     *  Paiements tab if it was genuinely a mistake. */
    #[Route('/{reservationId}/annuler-cloture', name: 'annuler_cloture', methods: ['POST'], requirements: ['reservationId' => '\d+'])]
    public function annulerCloture(
        int $reservationId,
        ReservationRepository $reservationRepo,
        VehicleReturnInspectionRepository $returnRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $reservation = $reservationRepo->find($reservationId);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }
        $this->assertBureauAccess($reservation);

        if ($reservation->getReservationStatus() !== 'terminee') {
            return $this->json(['error' => 'Ce contrat n\'est pas clôturé'], 400);
        }

        $inspection = $returnRepo->findOneBy(['reservation' => $reservation]);
        if (!$inspection) {
            return $this->json(['error' => 'Aucune inspection de retour trouvée'], 404);
        }

        $closedAt = $inspection->getEditAu();
        if (!$closedAt || (new \DateTimeImmutable())->getTimestamp() - $closedAt->getTimestamp() > 3600) {
            return $this->json([
                'error'   => 'undo_window_expired',
                'message' => 'L\'annulation de la clôture n\'est possible que dans l\'heure qui suit.',
            ], 403);
        }

        $em->remove($inspection);
        $this->reservationLifecycle->transition($reservation, 'en_cours', ['action' => 'undo_cloture']);

        // Voiture.voitureStatus may only be written via FleetLifecycleManager::applyEvent() —
        // re-sync now that the reservation is active again (likely back to "rented").
        $voiture = $reservation->getVoiture();
        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered(
                $voiture->getId(),
                $voiture->getBureau()?->getId(),
            ));
        }

        return $this->json(['success' => true, 'message' => 'Clôture annulée. Contrat réactivé.']);
    }

    // ── POST /api/location/{id}/annuler-livraison ────────────────────────────

    /** Reverses a hand-over — only within 1h of the original delivery (checked against
     *  VehicleDelivery's editAu, falling back to creeAu when it was never re-edited).
     *  Reverts the reservation status and deletes the VehicleDelivery record, then re-syncs
     *  the vehicle's lifecycle state. Deliberately does NOT delete the Contrat record — it
     *  already carries a real, sequential, possibly-already-printed contract number;
     *  redoing the hand-over later just reuses/updates that same record instead of
     *  burning the number. Also does NOT touch any payment collected at hand-over — money
     *  already received shouldn't silently un-record itself; staff remove it explicitly via
     *  the Paiements tab if it was genuinely a mistake. */
    #[Route('/{reservationId}/annuler-livraison', name: 'annuler_livraison', methods: ['POST'], requirements: ['reservationId' => '\d+'])]
    public function annulerLivraison(
        int $reservationId,
        ReservationRepository $reservationRepo,
        VehicleDeliveryRepository $deliveryRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $reservation = $reservationRepo->find($reservationId);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }
        $this->assertBureauAccess($reservation);

        if ($reservation->getReservationStatus() !== 'en_cours') {
            return $this->json(['error' => 'Ce contrat n\'est pas en cours'], 400);
        }

        $delivery = $deliveryRepo->findOneBy(['reservation' => $reservation]);
        if (!$delivery) {
            return $this->json(['error' => 'Aucune remise de véhicule trouvée'], 404);
        }

        $deliveredAt = $delivery->getEditAu() ?? $delivery->getCreeAu();
        if (!$deliveredAt || (new \DateTimeImmutable())->getTimestamp() - $deliveredAt->getTimestamp() > 3600) {
            return $this->json([
                'error'   => 'undo_window_expired',
                'message' => 'L\'annulation de la remise n\'est possible que dans l\'heure qui suit.',
            ], 403);
        }

        $em->remove($delivery);
        $this->reservationLifecycle->transition($reservation, 'confirmed', ['action' => 'undo_livraison']);

        // Voiture.voitureStatus may only be written via FleetLifecycleManager::applyEvent() —
        // re-sync now that the reservation is no longer actively delivered.
        $voiture = $reservation->getVoiture();
        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered(
                $voiture->getId(),
                $voiture->getBureau()?->getId(),
            ));
        }

        return $this->json(['success' => true, 'message' => 'Remise annulée. Réservation réactivée.']);
    }

    // ── POST /api/location/{id}/termine-avant-terme ─────────────────────────

    #[Route('/{reservationId}/termine-avant-terme', name: 'termine_avant_terme', methods: ['POST'], requirements: ['reservationId' => '\d+'])]
    public function termineAvantTerme(
        int $reservationId,
        Request $request,
        ReservationRepository $reservationRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $reservation = $reservationRepo->find($reservationId);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }
        $this->assertBureauAccess($reservation);
        if ($reservation->getReservationStatus() !== 'en_cours') {
            return $this->json(['error' => 'La réservation n\'est pas en cours'], 400);
        }

        $this->reservationLifecycle->transition($reservation, 'termine_avant_terme');

        // Voiture.voitureStatus may only be written via FleetLifecycleManager::applyEvent()
        // (enforced by VoitureStatusWriteGuard) — recomputes state from the now-closed
        // reservation instead of a raw direct write.
        $voiture = $reservation->getVoiture();
        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered(
                $voiture->getId(),
                $voiture->getBureau()?->getId(),
            ));
        }

        return $this->json(['success' => true, 'message' => 'Contrat terminé avant terme.']);
    }

    // ── Private serializers ─────────────────────────────────────────────────

    private function serializeReservation(
        \App\Entity\Reservation $r,
        ?\App\Entity\Voiture $voiture,
        ?\App\Entity\Client $client,
        ?\App\Entity\DeuxiemeChauffeur $dc,
        ?array $compliance
    ): array {
        return [
            'id'                => $r->getId(),
            'dateDebut'         => $r->getDateDebut()?->format('Y-m-d H:i:s'),
            'dateFin'           => $r->getDateFin()?->format('Y-m-d H:i:s'),
            'total'             => (float) ($r->getTotal() ?? 0),
            'montantPaye'       => (float) ($r->getMontantPaye() ?? 0),
            'modePaiement'      => $r->getModePaiement(),
            'lieuLivraison'     => $r->getLieuLivraison(),
            'lieuRetour'        => $r->getLieuRetour(),
            'prixParJour'       => $r->getPrixParJour() !== null ? (float) $r->getPrixParJour() : null,
            'reservationStatus' => $r->getReservationStatus(),
            'creeAu'            => $r->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'            => $r->getEditAu()?->format('Y-m-d H:i:s'),
            'accessoires'       => array_map(fn($a) => [
                'id'   => $a->getId(),
                'nom'  => $a->getNom(),
                'prix' => $a->getPrix() !== null ? (float) $a->getPrix() : null,
            ], $r->getAccessoires()->toArray()),
            'voiture' => $voiture ? [
                'id'                => $voiture->getId(),
                'marque'            => $voiture->getMarque(),
                'modele'            => $voiture->getModele(),
                'immatriculation'   => $voiture->getImmatriculation(),
                'prixJour'          => $voiture->getPrixJour() !== null ? (float) $voiture->getPrixJour() : null,
                'kilometrageActuel' => $voiture->getKilometrageActuel(),
                'compliance'        => $compliance,
            ] : null,
            'client' => $client ? [
                'id'                 => $client->getId(),
                'nom'                => $client->getNom(),
                'prenom'             => $client->getPrenom(),
                'telephone'          => $client->getTelephone(),
                'telephoneEtranger'  => $client->getTelephoneEtranger(),
                'cin'                => $client->getCin(),
                'cinDelivreLe'       => $client->getCinDelivreLe()?->format('Y-m-d'),
                'cinDelivreA'        => $client->getCinDelivreA(),
                'passeport'          => $client->getPasseport(),
                'passeportDelivreLe' => $client->getPasseportDelivreLe()?->format('Y-m-d'),
                'passeportDelivreA'  => $client->getPasseportDelivreA(),
                'permisConduite'     => $client->getPermisConduite(),
                'permisDelivreLe'    => $client->getPermisDelivreLe()?->format('Y-m-d'),
                'permisDelivreA'     => $client->getPermisDelivreA(),
                'nationalite'        => $client->getNationalite(),
                'dateNaissance'      => $client->getDateNaissance()?->format('Y-m-d'),
                'lieuNaissance'      => $client->getLieuNaissance(),
                'adresseMaroc'       => $client->getAdresseMaroc(),
                'adresseEtranger'    => $client->getAdresseEtranger(),
            ] : null,
            'deuxiemeChauffeur' => $dc ? [
                'id'                 => $dc->getId(),
                'nom'                => $dc->getNom(),
                'nationalite'        => $dc->getNationalite(),
                'cin'                => $dc->getCin(),
                'permisConduite'     => $dc->getPermisConduite(),
                'permisDelivreLe'    => $dc->getPermisDelivreLe()?->format('Y-m-d'),
                'permisDelivreA'     => $dc->getPermisDelivreA(),
                'passeport'          => $dc->getPasseport(),
                'passeportDelivreLe' => $dc->getPasseportDelivreLe()?->format('Y-m-d'),
                'passeportDelivreA'  => $dc->getPasseportDelivreA(),
                'dateNaissance'      => $dc->getDateNaissance()?->format('Y-m-d'),
                'adresseMaroc'       => $dc->getAdresseMaroc(),
                'telephone'          => $dc->getTelephone(),
                'adresseEtranger'    => $dc->getAdresseEtranger(),
                'telephoneEtranger'  => $dc->getTelephoneEtranger(),
            ] : null,
        ];
    }

    private function serializeContrat(Contrat $c): array
    {
        return [
            'id'                  => $c->getId(),
            'numero'              => $c->getNumero() ?? '',
            'hasCaution'          => $c->isHasCaution(),
            'cautionMontant'      => $c->getCautionMontant() !== null ? (float) $c->getCautionMontant() : null,
            'franchise'           => $c->getFranchise() !== null ? (float) $c->getFranchise() : null,
            'faitA'               => $c->getFaitA(),
            'signedAt'            => $c->getSignedAt()?->format('Y-m-d H:i:s'),
            'prixParJourSnapshot' => $c->getPrixParJourSnapshot() !== null ? (float) $c->getPrixParJourSnapshot() : null,
            'nbJoursFactures'     => $c->getNbJoursFactures(),
            'remise'              => (float) ($c->getRemise() ?? 0),
            'taxes'               => (float) ($c->getTaxes() ?? 0),
            'creeAu'              => $c->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'              => $c->getEditAu()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeDelivery(VehicleDelivery $d): array
    {
        return [
            'id'                         => $d->getId(),
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
            // Computed server-side (not derived from the naive, timezone-less timestamps
            // above) so the frontend's "Annuler" button can't drift out of sync with what
            // annulerLivraison() will actually accept — see that action for the same check.
            'canUndo' => (function () use ($d): bool {
                $deliveredAt = $d->getEditAu() ?? $d->getCreeAu();
                return $deliveredAt !== null
                    && (new \DateTimeImmutable())->getTimestamp() - $deliveredAt->getTimestamp() <= 3600;
            })(),
        ];
    }

    private function serializeInspection(VehicleReturnInspection $i, ?int $kmDepart = null): array
    {
        $res        = $i->getReservation();
        $kmRetour   = $i->getKilometrage();
        $kmEffectue = ($kmDepart !== null && $kmRetour !== null) ? max(0, $kmRetour - $kmDepart) : null;

        $joursFactures = VehicleReturnInspection::computeJoursFactures(
            $res->getDateDebut(), $res->getDateFin(), $i->getInspectedAt()
        );

        return [
            'id'                     => $i->getId(),
            'fuelLevelIn'            => $i->getFuelLevelIn(),
            'kilometrage'            => $kmRetour,
            'kmDepart'               => $kmDepart,
            'kmEffectue'             => $kmEffectue,
            'joursFactures'          => $joursFactures,
            'notes'                  => $i->getNotes(),
            'condition'              => $i->getCondition(),
            'fuelCharge'             => $i->getFuelCharge()         !== null ? (float) $i->getFuelCharge()         : null,
            'lateCharge'             => $i->getLateCharge()         !== null ? (float) $i->getLateCharge()         : null,
            'damageCharge'           => $i->getDamageCharge()       !== null ? (float) $i->getDamageCharge()       : null,
            'equipmentCharge'        => $i->getEquipmentCharge()    !== null ? (float) $i->getEquipmentCharge()    : null,
            'remiseMontant'          => $i->getRemiseMontant()      !== null ? (float) $i->getRemiseMontant()      : null,
            'remiseMotif'            => $i->getRemiseMotif(),
            'cautionRemboursee'      => $i->getCautionRemboursee()  !== null ? (float) $i->getCautionRemboursee()  : null,
            'totalAdditionalCharges' => $i->getTotalAdditionalCharges(),
            'totalSupplementaire'    => $i->getTotalSupplementaire(),
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
            'inspectedAt' => $i->getInspectedAt()->format('Y-m-d H:i:s'),
            'editAu'      => $i->getEditAu()?->format('Y-m-d H:i:s'),
            // Same reasoning as VehicleDelivery's canUndo — keeps the "Undo Closure" button
            // in sync with what annulerCloture() will actually accept.
            'canUndo' => (function () use ($i): bool {
                $closedAt = $i->getEditAu();
                return $closedAt !== null
                    && (new \DateTimeImmutable())->getTimestamp() - $closedAt->getTimestamp() <= 3600;
            })(),
        ];
    }

    private function buildTimeline(
        \App\Entity\Reservation $res,
        ?Contrat $contrat,
        ?VehicleDelivery $delivery,
        ?VehicleReturnInspection $inspection
    ): array {
        $status = $res->getReservationStatus();

        return [
            [
                'stage'     => 'created',
                'label'     => 'Créé',
                'date'      => $res->getCreeAu()?->format('Y-m-d H:i:s'),
                'completed' => true,
            ],
            [
                'stage'     => 'confirmed',
                'label'     => 'Contrat signé',
                'date'      => $contrat?->getSignedAt()?->format('Y-m-d H:i:s'),
                'completed' => $contrat !== null,
            ],
            [
                'stage'     => 'delivered',
                'label'     => 'Clés remises',
                'date'      => $delivery?->getCreeAu()?->format('Y-m-d H:i:s'),
                'completed' => $delivery !== null,
            ],
            [
                'stage'     => 'active',
                'label'     => 'En cours',
                'date'      => $delivery?->getCreeAu()?->format('Y-m-d H:i:s'),
                'completed' => in_array($status, ['en_cours', 'terminee', 'termine_avant_terme'], true),
            ],
            [
                'stage'     => 'returned',
                'label'     => 'Retourné',
                'date'      => $inspection?->getInspectedAt()?->format('Y-m-d H:i:s'),
                'completed' => $inspection !== null,
            ],
            [
                'stage'     => 'closed',
                'label'     => in_array($status, ['termine_avant_terme'], true) ? 'Terminé avant terme' : 'Clôturé',
                'date'      => in_array($status, ['terminee', 'termine_avant_terme'], true)
                    ? ($inspection?->getEditAu() ?? $inspection?->getInspectedAt())?->format('Y-m-d H:i:s')
                    : null,
                'completed' => in_array($status, ['terminee', 'termine_avant_terme'], true),
            ],
        ];
    }
}
