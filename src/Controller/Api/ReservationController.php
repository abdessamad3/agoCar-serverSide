<?php

namespace App\Controller\Api;

use App\Entity\DeuxiemeChauffeur;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Enum\StatusEnum;
use App\Repository\AccessoireRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\ClientRepository;
use App\Repository\VoitureRepository;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Service\ActivityLogService;
use App\Service\ComplianceService;
use App\Service\NotificationService;
use App\Service\VoitureStatusService;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservation', name: 'app_api_reservation_')]
#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private PaiementRepository    $paiementRepo,
        private VoitureStatusService  $statusService,
        private ActivityLogService    $activityLog,
        private NotificationService   $notificationService,
        private FleetLifecycleManager $flm,
    ) {}

    private function snapshotReservation(Reservation $r): array
    {
        return [
            'clientId'          => $r->getClient()?->getId(),
            'voitureId'         => $r->getVoiture()?->getId(),
            'dateDebut'         => $r->getDateDebut()?->format('Y-m-d H:i'),
            'dateFin'           => $r->getDateFin()?->format('Y-m-d H:i'),
            'total'             => $r->getTotal(),
            'montantPaye'       => $r->getMontantPaye(),
            'modePaiement'      => $r->getModePaiement(),
            'reservationStatus' => $r->getReservationStatus(),
        ];
    }

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

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId  = $this->getEffectiveBureauId();
        $clientId  = $request->query->get('clientId');
        $voitureId = (int) $request->query->get('voitureId', 0);
        $status    = trim((string) $request->query->get('status', ''));
        $page      = $this->getPageParam($request);
        $search    = trim((string) $request->query->get('search', ''));

        $qb = $em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->join('r.voiture', 'v')
            ->leftJoin('r.client', 'c')
            ->orderBy('r.dateDebut', 'DESC');

        if ($bureauId) {
            $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }
        if ($clientId) {
            $qb->andWhere('r.client = :clientId')->setParameter('clientId', (int) $clientId);
        }
        if ($voitureId) {
            $qb->andWhere('v.id = :voitureId')->setParameter('voitureId', $voitureId);
        }
        if ($status) {
            $qb->andWhere('r.reservationStatus = :status')->setParameter('status', $status);
        }
        if ($search) {
            $qb->andWhere('c.nom LIKE :s OR c.prenom LIKE :s OR c.telephone LIKE :s OR v.immatriculation LIKE :s OR v.marque LIKE :s')
               ->setParameter('s', "%$search%");
        }

        $serialize = fn($r) => [
            'id'               => $r->getId(),
            'client'           => ['id' => $r->getClient()?->getId(), 'nom' => $r->getClient()?->getNom(), 'prenom' => $r->getClient()?->getPrenom(), 'telephone' => $r->getClient()?->getTelephone()],
            'voiture'          => ['id' => $r->getVoiture()?->getId(), 'marque' => $r->getVoiture()?->getMarque(), 'modele' => $r->getVoiture()?->getModele(), 'immatriculation' => $r->getVoiture()?->getImmatriculation(), 'image' => $r->getVoiture()?->getImagePath()],
            'dateDebut'        => $r->getDateDebut()?->format('Y-m-d\TH:i'),
            'dateFin'          => $r->getDateFin()?->format('Y-m-d\TH:i'),
            'total'            => $r->getTotal(),
            'montantPaye'      => $r->getMontantPaye(),
            'montantRestant'   => $r->getMontantRestant(),
            'paymentStatus'    => $r->getPaymentStatus(),
            'modePaiement'     => $r->getModePaiement(),
            'reservationStatus'=> $r->getReservationStatus(),
            'lieuLivraison'    => $r->getLieuLivraison(),
            'lieuRetour'       => $r->getLieuRetour(),
            'prixParJour'      => $r->getPrixParJour() !== null ? (float) $r->getPrixParJour() : null,
            'paiementCount'    => $this->paiementRepo->countByReservation($r->getId()),
            'creeAu'           => $r->getCreeAu()?->format('Y-m-d H:i:s'),
        ];

        [$reservations, $total] = $this->paginateQb($qb, $page, $voitureId > 0);
        return $this->json(['data' => array_map($serialize, $reservations), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Reservation $reservation): JsonResponse
    {
        $this->assertBureauAccess($reservation);
        $total     = (float) $reservation->getTotal();
        $dateDebut = $reservation->getDateDebut();
        $dateFin   = $reservation->getDateFin();
        $days = ($dateDebut && $dateFin) ? (int) $dateDebut->diff($dateFin)->days : 0;
        $dc = $reservation->getDeuxiemeChauffeur();

        return $this->json([
            'id'                => $reservation->getId(),
            'client'            => [
                'id'              => $reservation->getClient()?->getId(),
                'nom'             => $reservation->getClient()?->getNom(),
                'telephone'       => $reservation->getClient()?->getTelephone(),
                'cin'             => $reservation->getClient()?->getCin(),
                'permisConduite'  => $reservation->getClient()?->getPermisConduite(),
                'nationalite'     => $reservation->getClient()?->getNationalite(),
                'passeport'       => $reservation->getClient()?->getPasseport(),
            ],
            'voiture'           => [
                'id'             => $reservation->getVoiture()?->getId(),
                'marque'         => $reservation->getVoiture()?->getMarque(),
                'modele'         => $reservation->getVoiture()?->getModele(),
                'annee'          => $reservation->getVoiture()?->getAnnee(),
                'immatriculation'=> $reservation->getVoiture()?->getImmatriculation(),
                'image'          => $reservation->getVoiture()?->getImagePath(),
            ],
            'dateDebut'         => $dateDebut?->format('Y-m-d\TH:i'),
            'dateFin'           => $dateFin?->format('Y-m-d\TH:i'),
            'nbJours'           => $days,
            'prixJour'          => (float) $reservation->getVoiture()?->getPrixJour(),
            'prixParJour'       => $reservation->getPrixParJour() !== null ? (float) $reservation->getPrixParJour() : null,
            'lieuLivraison'     => $reservation->getLieuLivraison(),
            'lieuRetour'        => $reservation->getLieuRetour(),
            'total'             => $total,
            'montantPaye'       => (float) $reservation->getMontantPaye(),
            'montantRestant'    => $reservation->getMontantRestant(),
            'montantSurpaye'    => $reservation->getMontantSurpaye(),
            'balance'           => $reservation->getBalance(),
            'paymentStatus'     => $reservation->getPaymentStatus(),
            'modePaiement'      => $reservation->getModePaiement(),
            'reservationStatus' => $reservation->getReservationStatus(),
            'accessoires'       => $reservation->getAccessoires()->map(fn($a) => ['id' => $a->getId(), 'nom' => $a->getNom()])->toArray(),
            'paiementCount'     => $this->paiementRepo->countByReservation($reservation->getId()),
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
            'creeAu'            => $reservation->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'            => $reservation->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        VoitureRepository $voitureRepo,
        AccessoireRepository $accessoireRepo,
        ComplianceService $complianceService,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $client  = $clientRepo->find($data['clientId']);
        $voiture = $voitureRepo->find($data['voitureId']);

        if (!$client || !$voiture) {
            return $this->json(['error' => 'Client ou voiture introuvable'], 404);
        }

        $dateDebut = new \DateTimeImmutable($data['dateDebut']);
        $dateFin   = new \DateTimeImmutable($data['dateFin']);

        // Only confirmed/in_progress reservations block creation — pending ones are just requests
        if ($reservationRepo->hasConfirmedOverlap($voiture->getId(), $dateDebut, $dateFin, 0)) {
            return $this->json([
                'error'   => 'double_booking',
                'message' => 'Ce véhicule est déjà confirmé sur cette période.',
            ], 409);
        }

        $compliance = $complianceService->getComplianceStatus($voiture);
        if (in_array($compliance['overall'], [ComplianceService::EXPIRED, ComplianceService::UNKNOWN])) {
            return $this->json([
                'error'      => 'compliance_blocked',
                'message'    => 'Ce véhicule ne peut pas être loué : un ou plusieurs documents ont expiré ou sont manquants.',
                'compliance' => $compliance,
            ], 422);
        }

        // ── Outstanding debt is informational only — never blocks booking creation.
        // The frontend shows it proactively next to client selection (same
        // non-blocking pattern as expired-document warnings) so staff and
        // managers alike see it up front, but a client's debt never prevents
        // a new reservation from being created. ──────────────────────────────
        $bureau = $voiture->getBureau();

        $reservation = new Reservation();
        $reservation->setClient($client);
        $reservation->setVoiture($voiture);
        $reservation->setBureau($bureau);
        $reservation->setDateDebut($dateDebut);
        $reservation->setDateFin($dateFin);
        $reservation->setTotal($data['total']);
        $reservation->setReservationStatus($data['reservationStatus'] ?? 'confirmed');
        $reservation->setMontantPaye('0');
        $reservation->setModePaiement($data['modePaiement'] ?? null);
        $reservation->setLieuLivraison($data['lieuLivraison'] ?? null);
        $reservation->setLieuRetour($data['lieuRetour'] ?? null);
        if (isset($data['prixParJour'])) {
            $reservation->setPrixParJour((string)(float)$data['prixParJour']);
        } elseif ($voiture->getPrixJour() !== null) {
            // Frontend doesn't always send prixParJour explicitly — fall back to the vehicle's
            // own daily rate so the dossier never shows "null MAD" for the daily rate.
            $reservation->setPrixParJour($voiture->getPrixJour());
        }
        $reservation->setCreeAu(new \DateTimeImmutable());

        if (!empty($data['deuxiemeChauffeur']) && is_array($data['deuxiemeChauffeur'])) {
            $dc = new DeuxiemeChauffeur();
            $this->applyDeuxiemeChauffeur($dc, $data['deuxiemeChauffeur']);
            $reservation->setDeuxiemeChauffeur($dc);
        }

        foreach ((array) ($data['accessoireIds'] ?? []) as $accId) {
            $acc = $accessoireRepo->find((int) $accId);
            if ($acc) $reservation->addAccessoire($acc);
        }

        $voiture->setReservationStatus('confirmed');

        $em->persist($reservation);
        $em->flush();

        // An initial payment taken at booking time gets a real Paiement record too —
        // never just a raw number on the reservation with no audit trail behind it.
        $montantInitial = (float) ($data['montantPaye'] ?? 0);
        if ($montantInitial > 0) {
            $paiement = new Paiement();
            $paiement->setMontant((string) $montantInitial);
            $paiement->setDatePaiement(new \DateTimeImmutable());
            $paiement->setStatut(StatusEnum::PAYEE);
            $paiement->setModePaiement($data['modePaiement'] ?? null);
            $paiement->setNote('Paiement initial à la réservation');
            $paiement->setCreeAu(new \DateTimeImmutable());
            $paiement->setCreePar($this->getUser());
            $paiement->setReservation($reservation);
            $em->persist($paiement);
            $reservation->setMontantPaye((string) $montantInitial);
            $em->flush();
        }

        // Cancel overlapping pending reservations and notify the confirming staff member
        if ($reservation->getReservationStatus() === 'confirmed') {
            $conflicts = $reservationRepo->findConflictingPending($voiture->getId(), $dateDebut, $dateFin, $reservation->getId());
            if ($conflicts) {
                $carLabel    = $voiture->getMarque() . ' ' . $voiture->getModele()
                             . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '');
                $clientLines = [];
                foreach ($conflicts as $conflicting) {
                    $conflicting->setReservationStatus('annulee');
                    $clientLines[] = trim($conflicting->getClient()?->getNom() ?? 'Client inconnu')
                                   . ' - ' . ($conflicting->getClient()?->getTelephone() ?? '—');
                }
                $em->flush();
                $staffUser = $this->getUser();
                if ($staffUser instanceof \App\Entity\Utilisateur) {
                    $this->notificationService->create(
                        $staffUser,
                        NotificationService::TYPE_RESERVATION_CONFLICT,
                        'reservation',
                        $reservation->getId(),
                        "Réservations annulées - $carLabel",
                        count($clientLines) . " réservation(s) annulée(s). Contactez les clients : "
                            . implode(' | ', $clientLines),
                        NotificationService::PRIORITY_HIGH,
                        '/location/' . $reservation->getId(),
                    );
                }
            }
        }

        $this->activityLog->logCreate('Reservation', $reservation->getId(), $this->snapshotReservation($reservation), $voiture->getBureau());
        $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        $clientName = trim($client->getNom() ?? '');
        $carLabel   = $voiture->getMarque() . ' ' . $voiture->getModele()
                    . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '');
        $this->notificationService->createForAllUsers(
            NotificationService::TYPE_RESERVATION_CREATED,
            'reservation',
            $reservation->getId(),
            "Nouvelle réservation - $carLabel",
            "Réservation créée pour $clientName, "
                . $reservation->getDateDebut()?->format('d/m/Y')
                . ' > ' . $reservation->getDateFin()?->format('d/m/Y'),
            NotificationService::PRIORITY_MEDIUM,
            '/location/' . $reservation->getId(),
        );

        return $this->json(['message' => 'Réservation créée', 'id' => $reservation->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        VoitureRepository $voitureRepo,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $this->assertBureauAccess($reservation);
        $data       = json_decode($request->getContent(), true) ?? [];

        // Optimistic concurrency: reject if the client's snapshot timestamp doesn't match DB
        if (isset($data['clientUpdatedAt'])) {
            $dbTs = $reservation->getEditAu()?->format('Y-m-d H:i:s')
                 ?? $reservation->getCreeAu()?->format('Y-m-d H:i:s');
            if ($data['clientUpdatedAt'] !== $dbTs) {
                return $this->json([
                    'error'           => 'concurrent_edit',
                    'message'         => "La réservation a été modifiée par quelqu'un d'autre. Veuillez recharger.",
                    'serverUpdatedAt' => $dbTs,
                ], 409);
            }
        }

        $oldSnap    = $this->snapshotReservation($reservation);
        $oldVoiture = $reservation->getVoiture();

        if (isset($data['clientId'])) {
            $client = $clientRepo->find($data['clientId']);
            if ($client) $reservation->setClient($client);
        }
        if (isset($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
            if ($voiture) $reservation->setVoiture($voiture);
        }
        if (isset($data['dateDebut'])) $reservation->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (isset($data['dateFin']))   $reservation->setDateFin(new \DateTimeImmutable($data['dateFin']));

        if (isset($data['dateDebut']) || isset($data['dateFin']) || isset($data['voitureId'])) {
            $voiture   = $reservation->getVoiture();
            $dateDebut = $reservation->getDateDebut();
            $dateFin   = $reservation->getDateFin();
            if ($voiture && $dateDebut && $dateFin) {
                if ($reservationRepo->hasOverlap($voiture->getId(), $dateDebut, $dateFin, $reservation->getId())) {
                    return $this->json([
                        'error'   => 'double_booking',
                        'message' => 'Ce véhicule est déjà réservé sur cette période.',
                    ], 409);
                }
            }
        }

        if (isset($data['total']))              $reservation->setTotal($data['total']);

        // ── Confirmation flow ────────────────────────────────────────────────────
        $cancelledClientLines = [];
        if (isset($data['reservationStatus']) && in_array($data['reservationStatus'], ['confirmed', 'confirmee'], true)
            && $reservation->getReservationStatus() === 'pending'
        ) {
            $voiture   = $reservation->getVoiture();
            $dateDebut = $reservation->getDateDebut();
            $dateFin   = $reservation->getDateFin();

            if ($voiture && $dateDebut && $dateFin) {
                // Block if a confirmed/in_progress reservation already holds this car
                if ($reservationRepo->hasConfirmedOverlap($voiture->getId(), $dateDebut, $dateFin, $reservation->getId())) {
                    return $this->json([
                        'error'   => 'double_booking',
                        'message' => 'Ce véhicule est déjà confirmé sur cette période. Veuillez choisir un autre véhicule ou modifier les dates.',
                    ], 409);
                }

                // Cancel overlapping pending reservations and collect client info for grouped notification
                $conflicts = $reservationRepo->findConflictingPending($voiture->getId(), $dateDebut, $dateFin, $reservation->getId());
                foreach ($conflicts as $conflicting) {
                    $conflicting->setReservationStatus('annulee');
                    $cancelledClientLines[] = trim($conflicting->getClient()?->getNom() ?? 'Client inconnu')
                                           . ' - ' . ($conflicting->getClient()?->getTelephone() ?? '—');
                }
            }
        }

        if (isset($data['reservationStatus']))  $reservation->setReservationStatus($data['reservationStatus']);
        if (isset($data['montantPaye']) && abs((float) $data['montantPaye'] - (float) $reservation->getMontantPaye()) > 0.001) {
            if ($this->paiementRepo->countByReservation($reservation->getId()) > 0) {
                return $this->json([
                    'error' => 'Le montant payé ne peut pas être modifié directement car un historique de paiement existe. Utilisez l\'historique des paiements.'
                ], 422);
            }
            // No payment history yet -- give this first amount a real Paiement
            // record instead of just stamping a raw number on the reservation.
            $montant = (float) $data['montantPaye'];
            if ($montant > 0) {
                $paiement = new Paiement();
                $paiement->setMontant((string) $montant);
                $paiement->setDatePaiement(new \DateTimeImmutable());
                $paiement->setStatut(StatusEnum::PAYEE);
                $paiement->setModePaiement($data['modePaiement'] ?? $reservation->getModePaiement());
                $paiement->setNote('Paiement initial à la réservation');
                $paiement->setCreeAu(new \DateTimeImmutable());
                $paiement->setCreePar($this->getUser());
                $paiement->setReservation($reservation);
                $em->persist($paiement);
            }
            $reservation->setMontantPaye((string) $montant);
        }
        if (isset($data['modePaiement']))       $reservation->setModePaiement($data['modePaiement']);
        if (array_key_exists('lieuLivraison', $data)) $reservation->setLieuLivraison($data['lieuLivraison']);
        if (array_key_exists('lieuRetour', $data))    $reservation->setLieuRetour($data['lieuRetour']);
        if (array_key_exists('prixParJour', $data)) {
            $reservation->setPrixParJour($data['prixParJour'] !== null ? (string)(float)$data['prixParJour'] : null);
        }

        if (array_key_exists('deuxiemeChauffeur', $data)) {
            if ($data['deuxiemeChauffeur'] === null) {
                $reservation->setDeuxiemeChauffeur(null);
            } elseif (is_array($data['deuxiemeChauffeur'])) {
                $dc = $reservation->getDeuxiemeChauffeur() ?? new DeuxiemeChauffeur();
                $this->applyDeuxiemeChauffeur($dc, $data['deuxiemeChauffeur']);
                $reservation->setDeuxiemeChauffeur($dc);
            }
        }

        $reservation->setEditAu(new \DateTimeImmutable());
        $em->flush();

        // Send one grouped notification to the confirming staff member listing all cancelled clients
        if ($cancelledClientLines) {
            $notifVoiture = $reservation->getVoiture();
            $carLabel     = $notifVoiture
                ? $notifVoiture->getMarque() . ' ' . $notifVoiture->getModele()
                    . ($notifVoiture->getImmatriculation() ? ' (' . $notifVoiture->getImmatriculation() . ')' : '')
                : 'véhicule';
            $staffUser = $this->getUser();
            if ($staffUser instanceof \App\Entity\Utilisateur) {
                $this->notificationService->create(
                    $staffUser,
                    NotificationService::TYPE_RESERVATION_CONFLICT,
                    'reservation',
                    $reservation->getId(),
                    "Réservations annulées - $carLabel",
                    count($cancelledClientLines) . " réservation(s) annulée(s). Contactez les clients : "
                        . implode(' | ', $cancelledClientLines),
                    NotificationService::PRIORITY_HIGH,
                    '/location/' . $reservation->getId(),
                );
            }
        }

        $this->activityLog->logUpdate('Reservation', $reservation->getId(), $oldSnap, $this->snapshotReservation($reservation), $reservation->getVoiture()?->getBureau());

        $newVoiture = $reservation->getVoiture();
        if ($newVoiture) {
            $this->flm->applyEvent($newVoiture, new TemporalSyncTriggered($newVoiture->getId(), $newVoiture->getBureau()?->getId()));
        }
        if ($oldVoiture && $oldVoiture !== $newVoiture) {
            $this->flm->applyEvent($oldVoiture, new TemporalSyncTriggered($oldVoiture->getId(), $oldVoiture->getBureau()?->getId()));
        }

        return $this->json(['message' => 'Réservation mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Reservation $reservation, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($reservation);
        $voiture = $reservation->getVoiture();
        $snap    = $this->snapshotReservation($reservation);
        $id      = $reservation->getId();
        $reservation->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        $this->activityLog->logDelete('Reservation', $id, $snap, $voiture?->getBureau());

        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));
        }

        return $this->json(['message' => 'Réservation supprimée'], 200);
    }

    private function applyDeuxiemeChauffeur(DeuxiemeChauffeur $dc, array $d): void
    {
        if (isset($d['nom']))               $dc->setNom($d['nom']);
        if (isset($d['nationalite']))       $dc->setNationalite($d['nationalite'] ?? null);
        if (isset($d['cin']))               $dc->setCin($d['cin'] ?? null);
        if (isset($d['permisConduite']))    $dc->setPermisConduite($d['permisConduite'] ?? null);
        if (isset($d['permisDelivreA']))    $dc->setPermisDelivreA($d['permisDelivreA'] ?? null);
        if (array_key_exists('permisDelivreLe', $d)) {
            $dc->setPermisDelivreLe($d['permisDelivreLe'] ? new \DateTimeImmutable($d['permisDelivreLe']) : null);
        }
        if (isset($d['passeport']))         $dc->setPasseport($d['passeport'] ?? null);
        if (isset($d['passeportDelivreA'])) $dc->setPasseportDelivreA($d['passeportDelivreA'] ?? null);
        if (array_key_exists('passeportDelivreLe', $d)) {
            $dc->setPasseportDelivreLe($d['passeportDelivreLe'] ? new \DateTimeImmutable($d['passeportDelivreLe']) : null);
        }
        if (array_key_exists('dateNaissance', $d)) {
            $dc->setDateNaissance($d['dateNaissance'] ? new \DateTimeImmutable($d['dateNaissance']) : null);
        }
        if (isset($d['adresseMaroc']))      $dc->setAdresseMaroc($d['adresseMaroc'] ?? null);
        if (isset($d['telephone']))         $dc->setTelephone($d['telephone'] ?? null);
        if (isset($d['adresseEtranger']))   $dc->setAdresseEtranger($d['adresseEtranger'] ?? null);
        if (isset($d['telephoneEtranger'])) $dc->setTelephoneEtranger($d['telephoneEtranger'] ?? null);
    }
}
