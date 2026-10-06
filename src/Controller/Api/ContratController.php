<?php

namespace App\Controller\Api;

use App\Entity\Contrat;
use App\Entity\VehicleDelivery;
use App\Entity\VehicleReturnInspection;
use App\Repository\ContratRepository;
use App\Repository\PaiementRepository;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contrat', name: 'app_api_contrat_')]
#[IsGranted('ROLE_USER')]
class ContratController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    private function serialize(Contrat $c): array
    {
        $res    = $c->getReservation();
        $voit   = $res?->getVoiture();
        $client = $res?->getClient();
        $dc     = $res?->getDeuxiemeChauffeur();

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
            'remise'              => (float) $c->getRemise(),
            'taxes'               => (float) $c->getTaxes(),
            'creeAu'              => $c->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'              => $c->getEditAu()?->format('Y-m-d H:i:s'),
            'extensions'          => array_map(fn($e) => [
                'id'       => $e->getId(),
                'dateFrom' => $e->getDateFrom()?->format('Y-m-d'),
                'dateTo'   => $e->getDateTo()?->format('Y-m-d'),
                'nbJours'  => $e->getNbJours(),
                'notes'    => $e->getNotes(),
            ], $c->getExtensions()->toArray()),
            'reservation' => $res ? [
                'id'                => $res->getId(),
                'dateDebut'         => $res->getDateDebut()?->format('Y-m-d H:i:s'),
                'dateFin'           => $res->getDateFin()?->format('Y-m-d H:i:s'),
                'total'             => $res->getTotal(),
                'montantPaye'       => $res->getMontantPaye(),
                'modePaiement'      => $res->getModePaiement(),
                'lieuLivraison'     => $res->getLieuLivraison(),
                'lieuRetour'        => $res->getLieuRetour(),
                'prixParJour'       => $res->getPrixParJour() !== null ? (float) $res->getPrixParJour() : null,
                'reservationStatus' => $res->getReservationStatus(),
                'voiture' => $voit ? [
                    'id'              => $voit->getId(),
                    'marque'          => $voit->getMarque(),
                    'modele'          => $voit->getModele(),
                    'immatriculation' => $voit->getImmatriculation(),
                    'prixJour'        => $voit->getPrixJour(),
                ] : null,
                'client' => $client ? [
                    'id'                 => $client->getId(),
                    'nom'                => $client->getNom(),
                    'telephone'          => $client->getTelephone(),
                    'telephoneEtranger'  => $client->getTelephoneEtranger(),
                    'cin'                => $client->getCin(),
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

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId      = $this->getEffectiveBureauId();
        $page          = $this->getPageParam($request);
        $reservationId = $request->query->get('reservationId');

        $qb = $em->createQueryBuilder()
            ->select('ct')
            ->from(Contrat::class, 'ct')
            ->join('ct.reservation', 'r')
            ->join('r.voiture', 'v')
            ->join('r.client', 'cli')
            ->orderBy('ct.creeAu', 'DESC');

        if ($bureauId) {
            $qb->where('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
        }
        if ($reservationId) {
            $qb->andWhere('r.id = :reservationId')->setParameter('reservationId', (int) $reservationId);
        }

        [$items, $total] = $this->paginateQb($qb, $page);
        return $this->json(['data' => array_map(fn($c) => $this->serialize($c), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    /**
     * Bureau-locked staff/managers may only touch contracts belonging to their
     * own bureau. True admins (getEffectiveBureauId() === null) are unrestricted,
     * matching the existing list() behavior.
     */
    private function assertBureauAccess(Contrat $contrat): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        $contratBureauId = $contrat->getReservation()?->getVoiture()?->getBureau()?->getId();
        if ($contratBureauId !== $bureauId) {
            throw $this->createNotFoundException('Contrat not found');
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Contrat $contrat): JsonResponse
    {
        $this->assertBureauAccess($contrat);
        return $this->json($this->serialize($contrat));
    }

    #[Route('/{id}/full', name: 'full', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function full(
        Contrat $contrat,
        VehicleDeliveryRepository $deliveryRepo,
        VehicleReturnInspectionRepository $returnRepo,
        PaiementRepository $paiementRepo
    ): JsonResponse {
        $this->assertBureauAccess($contrat);
        $reservation = $contrat->getReservation();

        $delivery   = $deliveryRepo->findOneBy(['reservation' => $reservation]);
        $inspection = $returnRepo->findOneBy(['reservation' => $reservation]);
        $paiements  = $reservation
            ? $paiementRepo->findBy(['reservation' => $reservation], ['datePaiement' => 'DESC'])
            : [];

        return $this->json([
            'contrat'                 => $this->serialize($contrat),
            'vehicleDelivery'         => $delivery   ? $this->serializeDelivery($delivery)                            : null,
            'vehicleReturnInspection' => $inspection ? $this->serializeInspection($inspection, $delivery?->getMileageOut()) : null,
            'paiements'               => array_map(fn($p) => [
                'id'           => $p->getId(),
                'montant'      => (float) $p->getMontant(),
                'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
                'modePaiement' => $p->getModePaiement(),
                'note'         => $p->getNote(),
            ], $paiements),
            'timeline' => $this->buildTimeline($contrat, $delivery, $inspection),
        ]);
    }

    private function serializeDelivery(VehicleDelivery $d): array
    {
        return [
            'id'                         => $d->getId(),
            'reservationId'              => $d->getReservation()?->getId(),
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
            'reservationId'          => $res->getId(),
            'fuelLevelIn'            => $i->getFuelLevelIn(),
            'kilometrage'            => $kmRetour,
            'kmDepart'               => $kmDepart,
            'kmEffectue'             => $kmEffectue,
            'joursFactures'          => $joursFactures,
            'notes'                  => $i->getNotes(),
            'condition'              => $i->getCondition(),
            'fuelCharge'             => $i->getFuelCharge()    !== null ? (float) $i->getFuelCharge()    : null,
            'lateCharge'             => $i->getLateCharge()    !== null ? (float) $i->getLateCharge()    : null,
            'damageCharge'           => $i->getDamageCharge()  !== null ? (float) $i->getDamageCharge()  : null,
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
            'reservationStatus'      => $res->getReservationStatus(),
        ];
    }

    private function buildTimeline(Contrat $c, ?VehicleDelivery $d, ?VehicleReturnInspection $i): array
    {
        $res    = $c->getReservation();
        $status = $res?->getReservationStatus();

        return [
            [
                'stage'     => 'created',
                'label'     => 'Créé',
                'date'      => $c->getCreeAu()?->format('Y-m-d H:i:s'),
                'agent'     => null,
                'completed' => true,
            ],
            [
                'stage'     => 'confirmed',
                'label'     => 'Confirmé',
                'date'      => $res?->getCreeAu()?->format('Y-m-d H:i:s'),
                'agent'     => null,
                'completed' => in_array($status, ['confirmed', 'en_cours', 'terminee'], true),
            ],
            [
                'stage'     => 'delivered',
                'label'     => 'Livré',
                'date'      => $d?->getCreeAu()?->format('Y-m-d H:i:s'),
                'agent'     => null,
                'completed' => $d !== null,
            ],
            [
                'stage'     => 'active',
                'label'     => 'Actif',
                'date'      => $d?->getCreeAu()?->format('Y-m-d H:i:s'),
                'agent'     => null,
                'completed' => in_array($status, ['en_cours', 'terminee'], true),
            ],
            [
                'stage'     => 'returned',
                'label'     => 'Retourné',
                'date'      => $i?->getInspectedAt()?->format('Y-m-d H:i:s'),
                'agent'     => null,
                'completed' => $i !== null,
            ],
            [
                'stage'     => 'closed',
                'label'     => 'Clôturé',
                'date'      => ($status === 'terminee')
                    ? ($i?->getEditAu() ?? $i?->getInspectedAt())?->format('Y-m-d H:i:s')
                    : null,
                'agent'     => null,
                'completed' => $status === 'terminee',
            ],
        ];
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo,
        ContratRepository $contratRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['reservationId'])) {
            return $this->json(['error' => 'reservationId is required'], Response::HTTP_BAD_REQUEST);
        }

        $reservation = $reservationRepo->find($data['reservationId']);
        if (!$reservation) {
            return $this->json(['error' => 'Reservation not found'], Response::HTTP_NOT_FOUND);
        }

        $existing = $contratRepo->findOneBy(['reservation' => $reservation]);
        if ($existing) {
            // Idempotent: return the existing contract so frontend can use its data
            return $this->json($this->serialize($existing), Response::HTTP_OK);
        }

        $contrat = new Contrat();
        $contrat->setReservation($reservation);

        // Auto-set prixParJourSnapshot from reservation at creation — never overwritten
        if ($reservation->getPrixParJour() !== null) {
            $contrat->setPrixParJourSnapshot($reservation->getPrixParJour());
        }

        // Auto-compute nbJoursFactures
        $dateDebut = $reservation->getDateDebut();
        $dateFin   = $reservation->getDateFin();
        if ($dateDebut && $dateFin) {
            $contrat->setNbJoursFactures((int) $dateDebut->diff($dateFin)->days ?: 1);
        }

        // Auto-set signedAt to now — the contract is created when signed
        $contrat->setSignedAt(new \DateTimeImmutable());

        // faitA: accept from request, default to bureau nom
        $faitA = $data['faitA'] ?? null;
        if (!$faitA) {
            $faitA = $reservation->getVoiture()?->getBureau()?->getNom() ?? '';
        }
        $contrat->setFaitA($faitA ?: null);

        $this->applyData($contrat, $data, $em);

        // numero is set by ContractNumberSubscriber on prePersist
        $em->persist($contrat);
        $em->flush();

        return $this->json($this->serialize($contrat), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Contrat $contrat, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($contrat);
        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyData($contrat, $data, $em, partial: true);
        $contrat->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json($this->serialize($contrat));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Contrat $contrat, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($contrat);
        $em->remove($contrat);
        $em->flush();
        return $this->json(['message' => 'Contrat supprimé']);
    }

    private function applyData(Contrat $contrat, array $data, EntityManagerInterface $em, bool $partial = false): void
    {
        $has = fn(string $k) => array_key_exists($k, $data);
        $val = fn(string $k, mixed $default = null) => $data[$k] ?? $default;

        if (!$partial || $has('hasCaution'))    $contrat->setHasCaution((bool) $val('hasCaution', false));
        if (!$partial || $has('cautionMontant')) $contrat->setCautionMontant($val('cautionMontant') !== null ? (string)(float)$val('cautionMontant') : null);
        if (!$partial || $has('franchise'))      $contrat->setFranchise($val('franchise') !== null ? (string)(float)$val('franchise') : null);
        if (!$partial || $has('faitA'))          $contrat->setFaitA($val('faitA'));
        if (!$partial || $has('remise'))         $contrat->setRemise($val('remise') !== null ? (string)(float)$val('remise') : '0.00');
        if (!$partial || $has('taxes'))          $contrat->setTaxes($val('taxes') !== null ? (string)(float)$val('taxes') : '0.00');
        // signedAt and prixParJourSnapshot are set at creation time only — never overwritten via PUT
    }
}
