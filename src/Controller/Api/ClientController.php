<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\ClientDocument;
use App\Entity\Reservation;
use App\Repository\ClientRepository;
use App\Repository\ClientDocumentRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Service\ActivityLogService;
use App\Trait\BureauAwareTrait;
use App\Trait\PaginationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/client', name: 'app_api_client_')]
class ClientController extends AbstractController
{
    use BureauAwareTrait;
    use PaginationTrait;

    public function __construct(
        private ActivityLogService $activityLog,
        private ReservationRepository $reservationRepo,
        private PaiementRepository $paiementRepo,
    ) {}

    private function snapshotClient(Client $c): array
    {
        return [
            'nom'            => $c->getNom(),
            'cin'            => $c->getCin(),
            'passeport'      => $c->getPasseport(),
            'permisConduite' => $c->getPermisConduite(),
            'nationalite'    => $c->getNationalite(),
            'telephone'      => $c->getTelephone(),
        ];
    }

    /** True when the given expiration date exists and is today or in the past. */
    private function isExpired(?\DateTimeImmutable $expiration): bool
    {
        return $expiration !== null && $expiration <= new \DateTimeImmutable('today');
    }

    private function serializeClient(Client $c, ?float $outstandingDebt = null): array
    {
        return [
            'id'                   => $c->getId(),
            'bureauId'             => $c->getBureau()?->getId(),
            'nom'                  => $c->getNom(),
            'prenom'               => $c->getPrenom(),
            'email'                => $c->getEmail(),
            'cin'                  => $c->getCin(),
            'cinDelivreLe'         => $c->getCinDelivreLe()?->format('Y-m-d'),
            'cinDelivreA'          => $c->getCinDelivreA(),
            'cinExpiration'        => $c->getCinExpiration()?->format('Y-m-d'),
            'cinExpired'           => $this->isExpired($c->getCinExpiration()),
            'passeport'            => $c->getPasseport(),
            'passeportDelivreLe'   => $c->getPasseportDelivreLe()?->format('Y-m-d'),
            'passeportDelivreA'    => $c->getPasseportDelivreA(),
            'passeportExpiration'  => $c->getPasseportExpiration()?->format('Y-m-d'),
            'passeportExpired'     => $this->isExpired($c->getPasseportExpiration()),
            'permisConduite'       => $c->getPermisConduite(),
            'permisDelivreLe'      => $c->getPermisDelivreLe()?->format('Y-m-d'),
            'permisDelivreA'       => $c->getPermisDelivreA(),
            'permisExpiration'     => $c->getPermisExpiration()?->format('Y-m-d'),
            'permisExpired'        => $this->isExpired($c->getPermisExpiration()),
            'nationalite'          => $c->getNationalite(),
            'telephone'            => $c->getTelephone(),
            'telephoneEtranger'    => $c->getTelephoneEtranger(),
            'dateNaissance'        => $c->getDateNaissance()?->format('Y-m-d'),
            'lieuNaissance'        => $c->getLieuNaissance(),
            'adresseMaroc'         => $c->getAdresseMaroc(),
            'adresseEtranger'      => $c->getAdresseEtranger(),
            'creeAu'               => $c->getCreeAu()?->format('Y-m-d H:i:s'),
            'outstandingDebt'      => $outstandingDebt ?? 0.0,
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $bureauId             = $this->getEffectiveBureauId();
        $page                 = $this->getPageParam($request);
        $search               = trim((string) $request->query->get('search', ''));
        $nationalite          = trim((string) $request->query->get('nationalite', ''));
        $hasDebt              = $request->query->getBoolean('hasDebt', false);
        $hasActiveReservation = $request->query->getBoolean('hasActiveReservation', false);
        $expiringDocs         = $request->query->getBoolean('expiringDocs', false);

        $qb = $em->createQueryBuilder()->select('c')->from(Client::class, 'c')->orderBy('c.id', 'DESC');

        if ($bureauId) {
            // Visible to a bureau if it created the client OR the client has a reservation
            // there — covers both "just added, no reservation yet" and "rented from us but
            // was originally created by another bureau".
            $subQb = $em->createQueryBuilder()
                ->select('rsc.id')
                ->from(Reservation::class, 'rs')
                ->join('rs.voiture', 'vs')
                ->join('rs.client', 'rsc')
                ->where('vs.bureau = :bureauId');
            $qb->where('c.bureau = :bureauId OR c.id IN (' . $subQb->getDQL() . ')')
               ->setParameter('bureauId', $bureauId);
        }
        if ($search) {
            $qb->andWhere('c.nom LIKE :s OR c.prenom LIKE :s OR c.cin LIKE :s OR c.telephone LIKE :s')
               ->setParameter('s', "%$search%");
        }
        if ($nationalite) {
            $qb->andWhere('c.nationalite = :nat')->setParameter('nat', $nationalite);
        }
        if ($hasDebt) {
            $sub = $em->createQueryBuilder()
                ->select('1')->from(Reservation::class, 'rd')
                ->where('rd.client = c')
                ->andWhere('rd.deletedAt IS NULL')
                ->andWhere('rd.reservationStatus NOT IN (:cancelStatuses)')
                ->andWhere('rd.total > COALESCE(rd.montantPaye, 0)')
                ->getDQL();
            $qb->andWhere($qb->expr()->exists($sub))
               ->setParameter('cancelStatuses', ['cancelled', 'annulee', 'annule']);
        }
        if ($hasActiveReservation) {
            $sub = $em->createQueryBuilder()
                ->select('1')->from(Reservation::class, 'ra')
                ->where('ra.client = c')
                ->andWhere('ra.reservationStatus IN (:activeStatuses)')
                ->getDQL();
            $qb->andWhere($qb->expr()->exists($sub))
               ->setParameter('activeStatuses', ['confirmed', 'in_progress']);
        }
        if ($expiringDocs) {
            $soon = new \DateTimeImmutable('+30 days');
            $qb->andWhere(
                '(c.cinExpiration IS NOT NULL AND c.cinExpiration <= :soon) OR
                 (c.passeportExpiration IS NOT NULL AND c.passeportExpiration <= :soon) OR
                 (c.permisExpiration IS NOT NULL AND c.permisExpiration <= :soon)'
            )->setParameter('soon', $soon);
        }

        [$clients, $total] = $this->paginateQb($qb, $page);

        $debts = $this->reservationRepo->getOutstandingDebtForClients(array_map(fn($c) => $c->getId(), $clients));

        return $this->json([
            'data' => array_map(fn($c) => $this->serializeClient($c, $debts[$c->getId()] ?? null), $clients),
            'meta' => $this->paginateMeta($total, $page),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Client $client): JsonResponse
    {
        $debt = $this->reservationRepo->getClientOutstandingDebt($client->getId());

        return $this->json(array_merge($this->serializeClient($client, $debt), [
            'editAu'  => $client->getEditAu()?->format('Y-m-d H:i:s'),
            'creePar' => $client->getCreePar()?->getId(),
        ]));
    }

    #[Route('/{id}/financial-summary', name: 'financial_summary', methods: ['GET'])]
    public function financialSummary(Client $client): JsonResponse
    {
        $summary = $this->reservationRepo->getClientFinancialSummary($client->getId());
        $lastPayment = $this->paiementRepo->getLastPaymentDate($client->getId());

        return $this->json(array_merge($summary, [
            'lastPaymentDate' => $lastPayment?->format('Y-m-d'),
        ]));
    }

    /** Field name ('cin'|'passeport'|'permisConduite') of the first duplicate found among other
     *  clients, or null. Checked explicitly (rather than relying on the DB unique constraint
     *  alone) so we can return a clear, field-specific error instead of a raw SQL failure. */
    private function findDuplicateField(EntityManagerInterface $em, array $data, ?int $excludeId): ?string
    {
        foreach (['cin', 'passeport', 'permisConduite'] as $field) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            if ($value === null || $value === '') continue;

            $qb = $em->createQueryBuilder()
                ->select('c.id')
                ->from(Client::class, 'c')
                ->where("c.$field = :v")
                ->setParameter('v', $value);
            if ($excludeId) $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);

            if ($qb->getQuery()->setMaxResults(1)->getOneOrNullResult()) {
                return $field;
            }
        }
        return null;
    }

    private function duplicateMessage(string $field): string
    {
        return match ($field) {
            'cin'            => 'Un client avec ce numéro de CIN existe déjà.',
            'passeport'      => 'Un client avec ce numéro de passeport existe déjà.',
            'permisConduite' => 'Un client avec ce numéro de permis de conduire existe déjà.',
            default          => 'Ce document est déjà associé à un autre client.',
        };
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if ($dupField = $this->findDuplicateField($em, $data, null)) {
            return $this->json(['error' => 'duplicate', 'field' => $dupField, 'message' => $this->duplicateMessage($dupField)], 409);
        }

        $client = new Client();
        $this->applyData($client, $data);
        $client->setCreeAu(new \DateTimeImmutable());
        /** @var \App\Entity\Utilisateur|null $user */
        $user = $this->getUser();
        $client->setCreePar($user);
        $client->setBureau($user?->getBureau());

        $em->persist($client);
        $em->flush();

        $this->activityLog->logCreate('Client', $client->getId(), $this->snapshotClient($client));

        return $this->json(['message' => 'Client créé', 'id' => $client->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Client $client, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if ($dupField = $this->findDuplicateField($em, $data, $client->getId())) {
            return $this->json(['error' => 'duplicate', 'field' => $dupField, 'message' => $this->duplicateMessage($dupField)], 409);
        }

        $oldSnap = $this->snapshotClient($client);

        $this->applyData($client, $data, partial: true);
        $client->setEditAu(new \DateTimeImmutable());

        $em->flush();

        $this->activityLog->logUpdate('Client', $client->getId(), $oldSnap, $this->snapshotClient($client));

        return $this->json(['message' => 'Client mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Client $client, EntityManagerInterface $em): JsonResponse
    {
        $count = $this->reservationRepo->count(['client' => $client]);
        if ($count > 0) {
            return $this->json([
                'message' => 'Impossible de supprimer ce client : il a ' . $count . ' réservation(s) liée(s).'
            ], 409);
        }

        $snap = $this->snapshotClient($client);
        $id   = $client->getId();
        $client->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        $this->activityLog->logDelete('Client', $id, $snap);

        return $this->json(['message' => 'Client supprimé'], 200);
    }

    #[Route('/{id}/documents', name: 'documents_list', methods: ['GET'])]
    public function listDocuments(Client $client, ClientDocumentRepository $repo): JsonResponse
    {
        $docs = $repo->findBy(['client' => $client], ['uploadedAt' => 'DESC']);
        $data = array_map(fn(ClientDocument $d) => [
            'id'           => $d->getId(),
            'documentType' => $d->getDocumentType(),
            'originalName' => $d->getOriginalName(),
            'url'          => '/uploads/client-documents/' . $d->getDocumentName(),
            'uploadedAt'   => $d->getUploadedAt()?->format('Y-m-d H:i:s'),
        ], $docs);

        return $this->json($data);
    }

    #[Route('/{id}/documents', name: 'documents_upload', methods: ['POST'])]
    public function uploadDocument(Client $client, Request $request, EntityManagerInterface $em, ClientDocumentRepository $repo): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG, WEBP ou PDF.'], 415);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'Fichier trop grand. Maximum 10 MB.'], 413);
        }

        $type = $request->request->get('documentType', 'autre');
        $allowed = ['cin', 'passeport', 'permis', 'autre'];
        if (!in_array($type, $allowed)) {
            $type = 'autre';
        }

        // CIN/passport/licence are single-current-photo "cards" — a fresh upload replaces the
        // old one(s) of that type. Soft-deleted (not erased), so the file stays on disk and the
        // record stays available for audit, but it drops out of all normal queries/listings.
        if (in_array($type, ['cin', 'passeport', 'permis'], true)) {
            foreach ($repo->findBy(['client' => $client, 'documentType' => $type]) as $old) {
                $old->setDeletedAt(new \DateTimeImmutable());
            }
        }

        $doc = new ClientDocument();
        $doc->setClient($client);
        $doc->setDocumentType($type);
        $doc->setOriginalName($file->getClientOriginalName());
        $doc->setDocumentFile($file);
        $doc->setUploadedAt(new \DateTimeImmutable());

        $em->persist($doc);
        $em->flush();

        return $this->json([
            'id'           => $doc->getId(),
            'documentType' => $doc->getDocumentType(),
            'originalName' => $doc->getOriginalName(),
            'url'          => '/uploads/client-documents/' . $doc->getDocumentName(),
            'uploadedAt'   => $doc->getUploadedAt()?->format('Y-m-d H:i:s'),
        ], 201);
    }

    #[Route('/{id}/documents/{docId}', name: 'documents_delete', methods: ['DELETE'])]
    public function deleteDocument(Client $client, int $docId, ClientDocumentRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $doc = $repo->find($docId);
        if (!$doc || $doc->getClient()->getId() !== $client->getId()) {
            return $this->json(['error' => 'Document not found'], 404);
        }

        $em->remove($doc);
        $em->flush();

        return $this->json(['message' => 'Document supprimé'], 204);
    }

    private function applyData(Client $client, array $data, bool $partial = false): void
    {
        $has = fn(string $k) => array_key_exists($k, $data);
        $val = fn(string $k, mixed $def = null) => $data[$k] ?? $def;
        // cin/passeport/permisConduite are unique columns — an empty string must become NULL,
        // otherwise two clients who both leave (say) passport blank would collide on ''.
        $uniqueVal = fn(string $k) => ($data[$k] ?? null) === '' ? null : ($data[$k] ?? null);

        if (!$partial || $has('nom'))               $client->setNom($val('nom', ''));
        if (!$partial || $has('prenom'))            $client->setPrenom($val('prenom'));
        if (!$partial || $has('email'))             $client->setEmail($val('email'));
        if (!$partial || $has('cin'))               $client->setCin($uniqueVal('cin'));
        if (!$partial || $has('passeport'))         $client->setPasseport($uniqueVal('passeport'));
        if (!$partial || $has('permisConduite'))    $client->setPermisConduite($uniqueVal('permisConduite'));
        if (!$partial || $has('nationalite'))       $client->setNationalite($val('nationalite'));
        if (!$partial || $has('telephone'))         $client->setTelephone($val('telephone'));
        if (!$partial || $has('telephoneEtranger')) $client->setTelephoneEtranger($val('telephoneEtranger'));
        if (!$partial || $has('adresseMaroc'))      $client->setAdresseMaroc($val('adresseMaroc', ''));
        if (!$partial || $has('adresseEtranger'))   $client->setAdresseEtranger($val('adresseEtranger'));
        if (!$partial || $has('lieuNaissance'))     $client->setLieuNaissance($val('lieuNaissance'));

        if (!$partial || $has('dateNaissance')) {
            $client->setDateNaissance($val('dateNaissance') ? new \DateTimeImmutable($val('dateNaissance')) : null);
        }
        if (!$partial || $has('permisDelivreLe')) {
            $client->setPermisDelivreLe($val('permisDelivreLe') ? new \DateTimeImmutable($val('permisDelivreLe')) : null);
        }
        if (!$partial || $has('permisDelivreA')) {
            $client->setPermisDelivreA($val('permisDelivreA'));
        }
        if (!$partial || $has('passeportDelivreLe')) {
            $client->setPasseportDelivreLe($val('passeportDelivreLe') ? new \DateTimeImmutable($val('passeportDelivreLe')) : null);
        }
        if (!$partial || $has('passeportDelivreA')) {
            $client->setPasseportDelivreA($val('passeportDelivreA'));
        }
        if (!$partial || $has('cinDelivreLe')) {
            $client->setCinDelivreLe($val('cinDelivreLe') ? new \DateTimeImmutable($val('cinDelivreLe')) : null);
        }
        if (!$partial || $has('cinDelivreA')) {
            $client->setCinDelivreA($val('cinDelivreA'));
        }
        if (!$partial || $has('cinExpiration')) {
            $client->setCinExpiration($val('cinExpiration') ? new \DateTimeImmutable($val('cinExpiration')) : null);
        }
        if (!$partial || $has('passeportExpiration')) {
            $client->setPasseportExpiration($val('passeportExpiration') ? new \DateTimeImmutable($val('passeportExpiration')) : null);
        }
        if (!$partial || $has('permisExpiration')) {
            $client->setPermisExpiration($val('permisExpiration') ? new \DateTimeImmutable($val('permisExpiration')) : null);
        }
    }
}
