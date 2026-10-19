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
            ->leftJoin('ri.reservation', 'resRet')
            ->leftJoin('dmg.vehicleDelivery', 'del')
            ->leftJoin('del.reservation', 'resDel')
            ->where(
                $em->createQueryBuilder()->expr()->orX(
                    'resRet.voiture = :voiture',
                    'resDel.voiture = :voiture',
                    'dmg.voiture = :voiture'
                )
            )
            ->setParameter('voiture', $voiture)
            ->orderBy('dmg.creeAu', 'DESC');

        $damages = $qb->getQuery()->getResult();

        return $this->json(array_map(fn(Damage $d) => $this->serialize($d, $em), $damages));
    }

    // ── POST /api/damage-photo ───────────────────────────────────────────────
    // Stateless photo upload for a damage mark on the delivery/return diagram —
    // the Damage row doesn't exist yet at that point (it's only created when the
    // delivery/return is saved), so this just stores the file and hands back a
    // path the caller attaches to the damage entry it sends on that save.

    #[Route('/damage-photo', name: 'upload_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $file = $request->files->get('photo');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['error' => 'Format d\'image non supporté.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'Image trop volumineuse (max 10 Mo).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/damage-photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $ext      = $file->guessExtension() ?? 'jpg';
        $fileName = uniqid('dmgphoto_') . '.' . $ext;
        $file->move($uploadDir, $fileName);

        return $this->json(['path' => '/uploads/damage-photos/' . $fileName], Response::HTTP_CREATED);
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

        $voiture     = $damage->getVoiture()
            ?? $damage->getReturnInspection()?->getReservation()?->getVoiture()
            ?? $damage->getVehicleDelivery()?->getReservation()?->getVoiture();
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

        // Fetch all damages (same logic as list() -- including the delivery join, so a
        // damage recorded only at hand-over still shows up in the report)
        $qb = $em->createQueryBuilder()
            ->select('dmg')
            ->from(Damage::class, 'dmg')
            ->leftJoin('dmg.returnInspection', 'ri')
            ->leftJoin('ri.reservation', 'resRet')
            ->leftJoin('dmg.vehicleDelivery', 'del')
            ->leftJoin('del.reservation', 'resDel')
            ->where(
                $em->createQueryBuilder()->expr()->orX(
                    'resRet.voiture = :voiture',
                    'resDel.voiture = :voiture',
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

        $html = $this->buildDamageReportHtml(
            $voitureData, $serializedDamages, $zoneLabels, $severityLabels,
            $openCount, $repairedCount, $totalCost, $inspectionData,
        );

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        // Without this, Dompdf's default chroot silently refuses to read the uploaded
        // damage photos (outside its own library path) and renders a broken-image icon
        // instead of the real photo, with no error — this is what that looked like.
        $options->set('chroot', realpath($this->getParameter('kernel.project_dir') . '/public'));

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
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /** Builds the damage report as plain HTML for Dompdf — same approach as every other
     *  PDF in this app (contract, client booking recap): inline styles, no flex/grid,
     *  no SVG. There is no Twig template for this; renderView() against a non-existent
     *  one was silently 500-ing every time the "Rapport PDF" button was clicked. */
    private function buildDamageReportHtml(
        array $voiture,
        array $damages,
        array $zoneLabels,
        array $severityLabels,
        int $openCount,
        int $repairedCount,
        float $totalCost,
        ?array $lastInspection,
    ): string {
        $esc = fn(?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
        $car = trim($esc($voiture['marque']) . ' ' . $esc($voiture['modele']) . ($voiture['annee'] ? ' ' . $voiture['annee'] : ''));

        $uploadRoot = $this->getParameter('kernel.project_dir') . '/public';
        $photoTag = function (?string $photo) use ($uploadRoot): string {
            if (!$photo) return '';
            $abs = $uploadRoot . $photo;
            if (!is_file($abs)) return '';
            return '<img src="' . htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') . '" class="dmgr-photo" />';
        };

        $rows = '';
        foreach ($damages as $d) {
            $zoneLabel = $zoneLabels[$d['zone']] ?? $d['zone'];
            $sevLabel  = $severityLabels[$d['severity']] ?? $d['severity'];
            $statusLabel = $d['status'] === 'repaired' ? 'Réparé' : 'Ouvert';
            $statusClass = $d['status'] === 'repaired' ? 'dmgr-status-repaired' : 'dmgr-status-open';
            $sourceLabel = match ($d['source'] ?? 'unknown') {
                'delivery' => 'Livraison', 'return' => 'Retour', 'manual' => 'Manuel', default => '—',
            };
            $cost = $d['repairMontant'] !== null ? number_format((float) $d['repairMontant'], 2, ',', ' ') . ' MAD' : '—';
            $date = $d['creeAu'] ? (new \DateTimeImmutable($d['creeAu']))->format('d/m/Y') : '—';

            $rows .= '<tr>'
                . '<td>' . $esc($zoneLabel) . '</td>'
                . '<td>' . $esc($sevLabel) . '</td>'
                . '<td>' . $esc($sourceLabel) . '</td>'
                . '<td>' . $esc($date) . '</td>'
                . '<td><span class="dmgr-badge ' . $statusClass . '">' . $statusLabel . '</span></td>'
                . '<td>' . $cost . '</td>'
                . '<td>' . $esc($d['description'] ?? '') . '</td>'
                . '<td>' . $photoTag($d['photo'] ?? null) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="8" class="dmgr-empty">Aucun dommage enregistré.</td></tr>';
        }

        $inspectionBlock = '';
        if ($lastInspection) {
            $condLabel = match ($lastInspection['condition']) {
                'major_damage' => 'Dommage majeur', 'minor_damage' => 'Dommage mineur', default => 'Propre',
            };
            $inspectionBlock = '<div class="dmgr-section-title">Dernière inspection de retour</div>'
                . '<table class="dmgr-kv">'
                . '<tr><td>État général</td><td>' . $esc($condLabel) . '</td></tr>'
                . '<tr><td>Kilométrage</td><td>' . $esc((string) ($lastInspection['kilometrage'] ?? '—')) . ' km</td></tr>'
                . '<tr><td>Niveau carburant</td><td>' . $esc($lastInspection['fuelLevel'] ?? '—') . '</td></tr>'
                . ($lastInspection['notes'] ? '<tr><td>Notes</td><td>' . $esc($lastInspection['notes']) . '</td></tr>' : '')
                . '</table>';
        }

        $totalCostStr = number_format($totalCost, 2, ',', ' ') . ' MAD';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'DejaVu Sans', sans-serif; color: #1e293b; margin: 0; padding: 0; font-size: 11px; }
    .dmgr-header { background: #0f172a; padding: 20px 28px; }
    .dmgr-brand { font-size: 18px; font-weight: bold; color: #ffffff; }
    .dmgr-brand .accent { color: #fbbf24; }
    .dmgr-subtitle { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 3px; }
    .dmgr-body { padding: 20px 28px; }
    .dmgr-section-title { font-size: 10px; font-weight: bold; color: #f59e0b; text-transform: uppercase;
                           letter-spacing: 1.5px; border-bottom: 2px solid #fef3c7; padding-bottom: 5px; margin: 16px 0 6px; }
    table.dmgr-kv { width: 100%; border-collapse: collapse; font-size: 11px; }
    table.dmgr-kv td:first-child { color: #64748b; width: 32%; padding: 5px 0; border-bottom: 1px solid #e2e8f0; }
    table.dmgr-kv td:last-child  { font-weight: bold; padding: 5px 0; border-bottom: 1px solid #e2e8f0; }
    .dmgr-stats { width: 100%; margin-top: 10px; }
    .dmgr-stat { display: inline-block; width: 31%; margin-right: 2%; background: #f8fafc; border: 1px solid #e2e8f0;
                 border-radius: 6px; padding: 10px; text-align: center; }
    .dmgr-stat-value { font-size: 18px; font-weight: bold; color: #0f172a; }
    .dmgr-stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    table.dmgr-list { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-top: 8px; }
    table.dmgr-list th { background: #f1f5f9; text-align: left; padding: 6px; border-bottom: 2px solid #cbd5e1; }
    table.dmgr-list td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .dmgr-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8.5px; font-weight: bold; }
    .dmgr-status-open { background: #fef3c7; color: #92400e; }
    .dmgr-status-repaired { background: #dcfce7; color: #166534; }
    .dmgr-empty { text-align: center; color: #94a3b8; padding: 16px; }
    .dmgr-photo { width: 110px; height: 110px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1; }
    .dmgr-footer { padding: 12px 28px; font-size: 9px; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>
  <div class="dmgr-header">
    <div class="dmgr-brand">AGO<span class="accent">CAR</span></div>
    <div class="dmgr-subtitle">Rapport des dommages véhicule</div>
  </div>

  <div class="dmgr-body">
    <div class="dmgr-section-title">Véhicule</div>
    <table class="dmgr-kv">
      <tr><td>Véhicule</td><td>{$car}</td></tr>
      <tr><td>Immatriculation</td><td>{$esc($voiture['immatriculation'])}</td></tr>
      <tr><td>Couleur</td><td>{$esc($voiture['couleur'])}</td></tr>
      <tr><td>Kilométrage actuel</td><td>{$esc((string) ($voiture['kilometrageActuel'] ?? '—'))} km</td></tr>
    </table>

    <div class="dmgr-stats">
      <div class="dmgr-stat"><div class="dmgr-stat-value">{$openCount}</div><div class="dmgr-stat-label">Dommages ouverts</div></div>
      <div class="dmgr-stat"><div class="dmgr-stat-value">{$repairedCount}</div><div class="dmgr-stat-label">Réparés</div></div>
      <div class="dmgr-stat"><div class="dmgr-stat-value">{$totalCostStr}</div><div class="dmgr-stat-label">Coût total réparations</div></div>
    </div>

    {$inspectionBlock}

    <div class="dmgr-section-title">Historique des dommages</div>
    <table class="dmgr-list">
      <tr>
        <th>Zone</th><th>Sévérité</th><th>Origine</th><th>Date</th><th>Statut</th><th>Coût</th><th>Description</th><th>Photo</th>
      </tr>
      {$rows}
    </table>
  </div>

  <div class="dmgr-footer">Généré le {$esc(date('d/m/Y à H:i'))} · AGOCAR</div>
</body>
</html>
HTML;
    }

    private function serialize(Damage $d, EntityManagerInterface $em): array
    {
        $ri  = $d->getReturnInspection();
        $del = $d->getVehicleDelivery();
        $res = $ri?->getReservation() ?? $del?->getReservation();

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
            'photo'             => $d->getPhoto(),
            'estimatedCost'     => $d->getEstimatedCost(),
            'reparationId'      => $d->getReparationId(),
            'depenseId'         => $depenseId,
            'repairMontant'     => $repairMontant,
            'repairMontantPaye' => $repairMontantPaye,
            'creeAu'            => $d->getCreeAu()?->format(\DateTimeInterface::ATOM),
            'repairedAt'        => $d->getRepairedAt()?->format(\DateTimeInterface::ATOM),
            'reservationId'     => $res?->getId(),
            'inspectionId'      => $ri?->getId(),
            'deliveryId'        => $del?->getId(),
            'source'            => match (true) {
                $d->getVoiture() !== null => 'manual',
                $ri !== null              => 'return',
                $del !== null             => 'delivery',
                default                   => 'unknown',
            },
        ];
    }
}
