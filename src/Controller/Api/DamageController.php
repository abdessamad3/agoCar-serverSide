<?php

namespace App\Controller\Api;

use App\Entity\Damage;
use App\Entity\Depense;
use App\Entity\PaiementDepense;
use App\Entity\Reparation;
use App\Entity\VehicleReturnInspection;
use App\Entity\Voiture;
use App\Enum\PaymentTypeEnum;
use App\Enum\StatusEnum;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Fleet\FleetLifecycleManager;
use App\Service\DepensePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'app_api_damage_')]
class DamageController extends AbstractController
{
    public function __construct(
        private FleetLifecycleManager  $flm,
        private DepensePaymentService  $paymentService,
    ) {}

    // ── GET /api/voiture/{id}/damages ────────────────────────────────────────

    #[Route('/voiture/{id}/damages', name: 'list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function list(int $id, EntityManagerInterface $em): JsonResponse
    {
        $voiture = $em->find(Voiture::class, $id);
        if (!$voiture) {
            return $this->json(['error' => 'Véhicule introuvable'], Response::HTTP_NOT_FOUND);
        }

        $qb = $em->createQueryBuilder()
            ->select('dmg')
            ->from(Damage::class, 'dmg')
            ->leftJoin('dmg.returnInspection', 'ri')
            ->leftJoin('ri.reservation', 'res')
            ->where(
                $em->createQueryBuilder()->expr()->orX(
                    'res.voiture = :voiture',
                    'dmg.voiture = :voiture'
                )
            )
            ->setParameter('voiture', $voiture)
            ->orderBy('dmg.creeAu', 'DESC');

        $damages = $qb->getQuery()->getResult();

        return $this->json(array_map(fn(Damage $d) => $this->serialize($d, $em), $damages));
    }

    // ── POST /api/voiture/{id}/damage ────────────────────────────────────────

    #[Route('/voiture/{id}/damage', name: 'create_manual', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createManual(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $voiture = $em->find(Voiture::class, $id);
        if (!$voiture) {
            return $this->json(['error' => 'Véhicule introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $zone = trim($data['zone'] ?? '');
        if (!$zone) {
            return $this->json(['error' => 'zone est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowed = [
            'front_bumper','hood','windshield','roof','rear_window','trunk','rear_bumper',
            'fl_fender','fr_fender','rl_fender','rr_fender',
            'fl_door','fr_door','rl_door','rr_door',
            'left_mirror','right_mirror',
            'fl_wheel','fr_wheel','rl_wheel','rr_wheel',
        ];
        if (!in_array($zone, $allowed, true)) {
            return $this->json(['error' => 'Zone invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $severity = $data['severity'] ?? 'scratch';
        if (!in_array($severity, ['scratch', 'dent', 'crack', 'broken'], true)) {
            $severity = 'scratch';
        }

        $dmg = new Damage();
        $dmg->setVoiture($voiture);
        $dmg->setZone($zone);
        $dmg->setSeverity($severity);
        $dmg->setDescription(trim($data['description'] ?? '') ?: null);

        if (!empty($data['estimatedCost']) && (float) $data['estimatedCost'] > 0) {
            $dmg->setEstimatedCost((string) round((float) $data['estimatedCost'], 2));
        }

        $em->persist($dmg);
        $em->flush();

        // ── Pending Depense/Reparation so it shows up in Maintenance immediately,
        //    not only once the repair is confirmed ──────────────────────────────
        if ($dmg->getEstimatedCost()) {
            $description = sprintf('Réparation: %s (%s)', $zone, $severity);

            $depense = new Depense();
            $depense->setTypeDepense('reparation');
            $depense->setDateDebut(new \DateTimeImmutable('today'));
            $depense->setMontant($dmg->getEstimatedCost());
            $depense->setMontantPaye('0');
            $depense->setStatut(StatusEnum::EN_ATTENTE);
            $depense->setDescription($description);
            $depense->setCreeAu(new \DateTimeImmutable());
            $depense->setCreePar($this->getUser());
            $depense->setVoiture($voiture);
            if ($voiture->getBureau()) $depense->setBureau($voiture->getBureau());
            $em->persist($depense);
            $em->flush();

            $reparation = new Reparation();
            $reparation->setDepense($depense);
            $reparation->setDescriptionTechnique($description);
            $reparation->setFilePaths([]);
            $reparation->setCreeAu(new \DateTimeImmutable());
            $reparation->setCreePar($this->getUser());
            $em->persist($reparation);
            $em->flush();

            $dmg->setReparationId($reparation->getId());
            $dmg->setDepenseId($depense->getId());
            $em->flush();
        }

        $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));

        return $this->json($this->serialize($dmg, $em), Response::HTTP_CREATED);
    }

    // ── POST /api/damage/{id}/repair ─────────────────────────────────────────

    #[Route('/damage/{id}/repair', name: 'repair', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function repair(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Damage|null $damage */
        $damage = $em->find(Damage::class, $id);
        if (!$damage) {
            return $this->json(['error' => 'Damage record not found'], Response::HTTP_NOT_FOUND);
        }

        if ($damage->getStatus() === 'repaired') {
            return $this->json(['error' => 'Already repaired'], Response::HTTP_BAD_REQUEST);
        }

        $damage->setStatus('repaired');
        $damage->setRepairedAt(new \DateTimeImmutable());

        $voiture     = $damage->getVoiture() ?? $damage->getReturnInspection()?->getReservation()?->getVoiture();
        $amountPaid  = (float) ($request->request->get('amountPaid', 0));
        $estimatedCost = $damage->getEstimatedCost() ? (float) $damage->getEstimatedCost() : null;
        $depenseMontant = $estimatedCost ?? ($amountPaid > 0 ? $amountPaid : 0);

        $zoneLabel   = $damage->getZone();
        $description = sprintf('Réparation: %s (%s)', $zoneLabel, $damage->getSeverity());

        // ── Reuse the pending Depense/Reparation created when the damage was
        //    logged (if any) instead of creating a duplicate financial record ──
        $depense    = $damage->getDepenseId() ? $em->find(Depense::class, $damage->getDepenseId()) : null;
        $reparation = $damage->getReparationId() ? $em->find(Reparation::class, $damage->getReparationId()) : null;

        if (!$depense) {
            $depense = new Depense();
            $depense->setTypeDepense('reparation');
            $depense->setDateDebut(new \DateTimeImmutable('today'));
            $depense->setStatut(StatusEnum::EN_ATTENTE);
            $depense->setDescription($description);
            $depense->setCreeAu(new \DateTimeImmutable());
            $depense->setCreePar($this->getUser());
            if ($voiture) {
                $depense->setVoiture($voiture);
                if ($voiture->getBureau()) $depense->setBureau($voiture->getBureau());
            }
            $em->persist($depense);
        }
        $depense->setDateFin(new \DateTimeImmutable('today'));
        $depense->setMontant((string) round($depenseMontant, 2));
        $em->flush();

        // ── Handle receipt file ──────────────────────────────────────────────
        $filePaths = $reparation?->getFilePaths() ?? [];
        $file = $request->files->get('receipt');
        if ($file) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
            if (in_array($file->getMimeType(), $allowedMimes, true) && $file->getSize() <= 10 * 1024 * 1024) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/damage-receipts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $ext      = $file->guessExtension() ?? 'bin';
                $fileName = uniqid('dmg_') . '.' . $ext;
                $file->move($uploadDir, $fileName);
                $filePaths[] = '/uploads/damage-receipts/' . $fileName;
            }
        }

        // ── Create (or update) the Reparation record ──────────────────────────
        if (!$reparation) {
            $reparation = new Reparation();
            $reparation->setDepense($depense);
            $reparation->setCreeAu(new \DateTimeImmutable());
            $reparation->setCreePar($this->getUser());
            $em->persist($reparation);
        }
        $reparation->setDescriptionTechnique($description);
        $reparation->setFilePaths($filePaths);
        $em->flush();

        $damage->setReparationId($reparation->getId());
        $damage->setDepenseId($depense->getId());

        // ── Record initial payment installment if amount provided ────────────
        if ($amountPaid > 0) {
            $initialPayment = min($amountPaid, $depenseMontant > 0 ? $depenseMontant : $amountPaid);
            $paiement = new PaiementDepense();
            $paiement->setDepense($depense);
            $paiement->setMontant((string) round($initialPayment, 2));
            $paiement->setDatePaiement(new \DateTimeImmutable());
            $paiement->setPaymentType(PaymentTypeEnum::INSTALLMENT);
            $paiement->setPaymentReference($this->paymentService->generateReference($em));
            $paiement->setCreeAu(new \DateTimeImmutable());
            $paiement->setCreePar($this->getUser());
            $em->persist($paiement);
            $em->flush();

            $this->paymentService->recalculate($depense->getId(), $em);
        }

        $em->persist($damage);
        $em->flush();

        // ── Check if all damages are repaired ────────────────────────────────
        $allRepaired = false;
        if ($voiture) {
            $qb = $em->createQueryBuilder();
            $openCount = (int) $qb
                ->select('COUNT(d.id)')
                ->from(Damage::class, 'd')
                ->leftJoin('d.returnInspection', 'ri')
                ->leftJoin('ri.reservation', 'res')
                ->where($qb->expr()->orX('res.voiture = :voiture', 'd.voiture = :voiture'))
                ->andWhere('d.status = :open')
                ->setParameter('voiture', $voiture)
                ->setParameter('open', 'open')
                ->getQuery()->getSingleScalarResult();

            if ($openCount === 0) {
                $allRepaired = true;
                $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));
            }
        }

        return $this->json(['damage' => $this->serialize($damage, $em), 'allRepaired' => $allRepaired]);
    }

    // ── GET /api/damage (unfiltered — lists every damage record, for cleanup/admin use) ──

    #[Route('/damage', name: 'list_all', methods: ['GET'])]
    public function listAll(EntityManagerInterface $em): JsonResponse
    {
        $damages = $em->createQueryBuilder()
            ->select('dmg')->from(Damage::class, 'dmg')
            ->orderBy('dmg.creeAu', 'DESC')
            ->getQuery()->getResult();

        return $this->json(array_map(fn(Damage $d) => $this->serialize($d, $em), $damages));
    }

    // ── DELETE /api/damage/{id} ──────────────────────────────────────────────

    #[Route('/damage/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $damage = $em->find(Damage::class, $id);
        if (!$damage) {
            return $this->json(['error' => 'Damage record not found'], Response::HTTP_NOT_FOUND);
        }

        $voiture = $damage->getVoiture() ?? $damage->getReturnInspection()?->getReservation()?->getVoiture();

        // Clean up the pending Depense/Reparation created when this (unrepaired)
        // damage was logged, so deleting it doesn't leave a phantom expense behind.
        if ($damage->getStatus() !== 'repaired' && $damage->getDepenseId()) {
            $reparation = $damage->getReparationId() ? $em->find(Reparation::class, $damage->getReparationId()) : null;
            $depense    = $em->find(Depense::class, $damage->getDepenseId());
            if ($reparation) $em->remove($reparation);
            if ($depense)    $em->remove($depense);
        }

        $em->remove($damage);
        $em->flush();

        if ($voiture) {
            $this->flm->applyEvent($voiture, new TemporalSyncTriggered($voiture->getId(), $voiture->getBureau()?->getId()));
        }

        return $this->json(['message' => 'Dommage supprimé']);
    }

    // ── GET /api/voiture/{id}/damage-report ─────────────────────────────────

    #[Route('/voiture/{id}/damage-report', name: 'report', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function damageReport(int $id, EntityManagerInterface $em): Response
    {
        $voiture = $em->find(Voiture::class, $id);
        if (!$voiture) {
            return new Response('Véhicule introuvable', Response::HTTP_NOT_FOUND);
        }

        // Fetch all damages (same logic as list)
        $qb = $em->createQueryBuilder()
            ->select('dmg')
            ->from(Damage::class, 'dmg')
            ->leftJoin('dmg.returnInspection', 'ri')
            ->leftJoin('ri.reservation', 'res')
            ->where(
                $em->createQueryBuilder()->expr()->orX(
                    'res.voiture = :voiture',
                    'dmg.voiture = :voiture'
                )
            )
            ->setParameter('voiture', $voiture)
            ->orderBy('dmg.creeAu', 'ASC');

        $damages = $qb->getQuery()->getResult();

        // Last VehicleReturnInspection for this voiture
        $lastInspection = $em->createQueryBuilder()
            ->select('ri')
            ->from(VehicleReturnInspection::class, 'ri')
            ->innerJoin('ri.reservation', 'res')
            ->where('res.voiture = :voiture')
            ->setParameter('voiture', $voiture)
            ->orderBy('ri.inspectedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $zoneLabels = [
            'front_bumper'  => 'Pare-choc avant',
            'hood'          => 'Capot',
            'windshield'    => 'Pare-brise',
            'roof'          => 'Toit',
            'rear_window'   => 'Lunette arrière',
            'trunk'         => 'Coffre',
            'rear_bumper'   => 'Pare-choc arrière',
            'fl_fender'     => 'Aile av. gauche',
            'fr_fender'     => 'Aile av. droite',
            'rl_fender'     => 'Aile ar. gauche',
            'rr_fender'     => 'Aile ar. droite',
            'fl_door'       => 'Portière av. gauche',
            'fr_door'       => 'Portière av. droite',
            'rl_door'       => 'Portière ar. gauche',
            'rr_door'       => 'Portière ar. droite',
            'left_mirror'   => 'Rétroviseur gauche',
            'right_mirror'  => 'Rétroviseur droit',
            'fl_wheel'      => 'Roue av. gauche',
            'fr_wheel'      => 'Roue av. droite',
            'rl_wheel'      => 'Roue ar. gauche',
            'rr_wheel'      => 'Roue ar. droite',
        ];

        $severityLabels = [
            'scratch' => 'Rayure',
            'dent'    => 'Bosse',
            'crack'   => 'Fissure',
            'broken'  => 'Cassure',
        ];

        // Build serialized damage list with resolved repair cost
        $serializedDamages = array_map(fn(Damage $d) => $this->serialize($d, $em), $damages);

        $openCount     = count(array_filter($serializedDamages, fn($d) => $d['status'] !== 'repaired'));
        $repairedCount = count(array_filter($serializedDamages, fn($d) => $d['status'] === 'repaired'));
        $totalCost     = array_sum(array_map(fn($d) => (float) ($d['repairMontant'] ?? 0), $serializedDamages));

        // Build inspection data array for Twig
        $inspectionData = null;
        if ($lastInspection instanceof VehicleReturnInspection) {
            $inspectionData = [
                'fuelLevel'  => $lastInspection->getFuelLevelIn(),
                'kilometrage' => $lastInspection->getKilometrage(),
                'condition'  => $lastInspection->getCondition(),
                'notes'      => $lastInspection->getNotes(),
            ];
        }

        $voitureData = [
            'id'               => $voiture->getId(),
            'marque'           => $voiture->getMarque(),
            'modele'           => $voiture->getModele(),
            'immatriculation'  => $voiture->getImmatriculation(),
            'couleur'          => $voiture->getCouleur(),
            'annee'            => $voiture->getAnnee(),
            'kilometrageActuel' => $voiture->getKilometrageActuel(),
            'bureau'           => $voiture->getBureau(),
        ];

        $html = $this->renderView('pdf/damage_report.html.twig', [
            'voiture'        => $voitureData,
            'damages'        => $serializedDamages,
            'zoneLabels'     => $zoneLabels,
            'severityLabels' => $severityLabels,
            'openCount'      => $openCount,
            'repairedCount'  => $repairedCount,
            'totalCost'      => $totalCost,
            'lastInspection' => $inspectionData,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('rapport-dommages-%s-%s.pdf',
            strtolower(str_replace(' ', '-', $voiture->getImmatriculation() ?? $voiture->getId())),
            date('Ymd')
        );

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            ]
        );
    }

    private function serialize(Damage $d, EntityManagerInterface $em): array
    {
        $ri  = $d->getReturnInspection();
        $res = $ri?->getReservation();

        // Resolve depense: prefer direct depenseId, fallback to reparationId → Reparation → Depense
        $depenseId         = $d->getDepenseId();
        $repairMontant     = null;
        $repairMontantPaye = null;

        if (!$depenseId && $d->getReparationId()) {
            $rep = $em->find(Reparation::class, $d->getReparationId());
            $depenseId = $rep?->getDepense()?->getId();
        }

        if ($depenseId) {
            $dep = $em->find(Depense::class, $depenseId);
            if ($dep) {
                $repairMontant     = (float) ($dep->getMontant() ?? 0);
                $repairMontantPaye = (float) ($dep->getMontantPaye() ?? 0);
            }
        }

        return [
            'id'                => $d->getId(),
            'zone'              => $d->getZone(),
            'severity'          => $d->getSeverity(),
            'status'            => $d->getStatus(),
            'description'       => $d->getDescription(),
            'estimatedCost'     => $d->getEstimatedCost(),
            'reparationId'      => $d->getReparationId(),
            'depenseId'         => $depenseId,
            'repairMontant'     => $repairMontant,
            'repairMontantPaye' => $repairMontantPaye,
            'creeAu'            => $d->getCreeAu()?->format(\DateTimeInterface::ATOM),
            'repairedAt'        => $d->getRepairedAt()?->format(\DateTimeInterface::ATOM),
            'reservationId'     => $res?->getId(),
            'inspectionId'      => $ri?->getId(),
            'source'            => $d->getVoiture() !== null ? 'manual' : 'inspection',
        ];
    }
}
