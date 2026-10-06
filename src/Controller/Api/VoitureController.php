<?php

namespace App\Controller\Api;

use App\Entity\Voiture;
use App\Entity\VoitureImage;
use App\Entity\Reservation;
use App\Entity\Depense;
use App\Entity\Assurance;
use App\Entity\Vignette;
use App\Entity\SuiviTechnique;
use App\Entity\Vidange;
use App\Repository\VoitureRepository;
use App\Repository\BureauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Entity\User;
use App\Fleet\FleetLifecycleManager;
use App\Fleet\Event\DecommissionInitiated;
use App\Fleet\Event\TemporalSyncTriggered;
use App\Fleet\Event\VehicleSetupStarted;
use App\Fleet\Exception\InvalidTransitionException;
use App\Fleet\Exception\LifecycleViolationException;
use App\Doctrine\VoitureStatusWriteGuard;
use App\Service\ActivityLogService;
use App\Service\ComplianceService;
use App\Service\OilChangeService;
use App\Service\VoitureStatusService;
use App\Trait\BureauAwareTrait;

#[Route('/api/voiture', name: 'app_api_voiture_')]
#[IsGranted('ROLE_USER')]
class VoitureController extends AbstractController
{
    use BureauAwareTrait;

    public function __construct(
        private KernelInterface          $kernel,
        private ComplianceService        $complianceService,
        private OilChangeService         $oilChangeService,
        private VoitureStatusService     $statusService,
        private ActivityLogService       $activityLog,
        private FleetLifecycleManager    $flm,
        private VoitureStatusWriteGuard  $guard,
    ) {}

    private function snapshotVoiture(Voiture $v): array
    {
        return [
            'marque'                  => $v->getMarque(),
            'modele'                  => $v->getModele(),
            'version'                 => $v->getVersion(),
            'annee'                   => $v->getAnnee(),
            'immatriculation'         => $v->getImmatriculation(),
            'vin'                     => $v->getVin(),
            'typeCarburant'           => $v->getTypeCarburant(),
            'transmission'            => $v->getTransmission(),
            'couleur'                 => $v->getCouleur(),
            'places'                  => $v->getPlaces(),
            'portes'                  => $v->getPortes(),
            'puissanceCv'             => $v->getPuissanceCv(),
            'categorie'               => $v->getCategorie(),
            'kilometrageActuel'       => $v->getKilometrageActuel(),
            'climatisation'           => $v->isClimatisation(),
            'prixJour'                => $v->getPrixJour(),
            'prixSemaine'             => $v->getPrixSemaine(),
            'prixMois'                => $v->getPrixMois(),
            'prixAchat'               => $v->getPrixAchat(),
            'dateAchat'               => $v->getDateAchat()?->format('Y-m-d'),
            'dateExpirationAssurance' => $v->getDateExpirationAssurance()?->format('Y-m-d'),
            'dateExpirationVignette'  => $v->getDateExpirationVignette()?->format('Y-m-d'),
            'dateExpirationVisite'    => $v->getDateExpirationVisite()?->format('Y-m-d'),
            'voitureStatus'           => $v->getVoitureStatus(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, VoitureRepository $repo): JsonResponse
    {
        try {
            // 1. Get query parameters
            $page = (int) $request->query->get('page', 1);
            $limit = min(1000, max(1, (int) $request->query->get('limit', 20)));
            $search = $request->query->get('search', '');
            $voitureStatus = $request->query->get('voitureStatus', '');
            $typeCarburant = $request->query->get('typeCarburant', '');
            $sort = $request->query->get('sort', 'id');
            $direction = $request->query->get('direction', 'ASC');
            $dateDebutStr = $request->query->get('dateDebut', '');
            $dateFinStr   = $request->query->get('dateFin', '');
            $bureauId     = $this->getEffectiveBureauId();

            // Validate pagination
            $page = max(1, $page);
            $direction = in_array(strtoupper($direction), ['ASC', 'DESC']) ? strtoupper($direction) : 'ASC';

            // 2. Build filters array
            $filters = [
                'search'        => $search,
                'voitureStatus' => $voitureStatus,
                'typeCarburant' => $typeCarburant,
                'sort'          => $sort,
                'direction'     => $direction,
                'bureauId'      => $bureauId ?: null,
            ];

            // 3. Get voitures and total count
            $voitures = $repo->findWithFilters($filters, $page, $limit);
            $total = $repo->countWithFilters($filters);
            $pages = ceil($total / $limit);

            // 3b. Availability check for date range
            $bookedIds = [];
            $datesActive = false;
            if ($dateDebutStr && $dateFinStr) {
                try {
                    $dateDebut = new \DateTimeImmutable($dateDebutStr);
                    $dateFin   = new \DateTimeImmutable($dateFinStr);
                    if ($dateDebut <= $dateFin) {
                        $datesActive = true;
                        $bookedIds = $repo->findBookedVoitureIdsForPeriod($dateDebut, $dateFin);
                    }
                } catch (\Exception) {}
            }

            // 4. Compute effective statuses + compliance + oil change in bulk
            $statusMap     = $this->statusService->computeStatusBulk($voitures);
            $complianceMap = $this->complianceService->getComplianceStatusBulk($voitures);
            $oilMap        = $this->oilChangeService->getOilStatusBulk($voitures);

            // 5. Format response
            $data = array_map(fn($v) => [
                'id'                       => $v->getId(),
                'marque'                   => $v->getMarque(),
                'modele'                   => $v->getModele(),
                'version'                  => $v->getVersion(),
                'annee'                    => $v->getAnnee(),
                'immatriculation'          => $v->getImmatriculation(),
                'vin'                      => $v->getVin(),
                'typeCarburant'            => $v->getTypeCarburant(),
                'transmission'             => $v->getTransmission(),
                'couleur'                  => $v->getCouleur(),
                'places'                   => $v->getPlaces(),
                'portes'                   => $v->getPortes(),
                'puissanceCv'              => $v->getPuissanceCv(),
                'categorie'                => $v->getCategorie(),
                'climatisation'            => $v->isClimatisation(),
                'kilometrageActuel'        => $v->getKilometrageActuel(),
                'prixJour'                 => $v->getPrixJour(),
                'prixSemaine'              => $v->getPrixSemaine(),
                'prixMois'                 => $v->getPrixMois(),
                'prixAchat'                => $v->getPrixAchat(),
                'dateAchat'                => $v->getDateAchat()?->format('Y-m-d'),
                'dateExpirationAssurance'  => $v->getDateExpirationAssurance()?->format('Y-m-d'),
                'dateExpirationVignette'   => $v->getDateExpirationVignette()?->format('Y-m-d'),
                'dateExpirationVisite'     => $v->getDateExpirationVisite()?->format('Y-m-d'),
                'voitureStatus'            => $v->getVoitureStatus(),
                'effectiveStatus'          => $statusMap[$v->getId()] ?? $v->getVoitureStatus(),
                'bureau'                   => $v->getBureau()?->getId(),
                'image'                    => $v->getImagePath(),
                'galleryImages'            => array_values(array_map(
                    fn($img) => ['id' => $img->getId(), 'path' => $img->getImagePath()],
                    $v->getImages()->toArray()
                )),
                'availabilityForPeriod'    => $datesActive
                    ? (in_array($v->getId(), $bookedIds) ? 'reservee' : 'disponible')
                    : null,
                'compliance'               => $complianceMap[$v->getId()] ?? $this->complianceService->getComplianceStatus($v),
                'oilChange'                => $oilMap[$v->getId()] ?? $this->oilChangeService->getOilStatus($v),
            ], $voitures);

            return $this->json([
                'data' => $data,
                'meta' => [
                    'total'       => $total,
                    'page'        => $page,
                    'limit'       => $limit,
                    'totalPages'  => (int) ceil($total / $limit) ?: 1,
                    'hasNextPage' => $page < ((int) ceil($total / $limit) ?: 1),
                    'hasPrevPage' => $page > 1,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch vehicles',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Voiture $voiture): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId !== null && $voiture->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Voiture introuvable');
        }
        try {
            return $this->json([
                'id'                       => $voiture->getId(),
                'marque'                   => $voiture->getMarque(),
                'modele'                   => $voiture->getModele(),
                'version'                  => $voiture->getVersion(),
                'annee'                    => $voiture->getAnnee(),
                'immatriculation'          => $voiture->getImmatriculation(),
                'vin'                      => $voiture->getVin(),
                'typeCarburant'            => $voiture->getTypeCarburant(),
                'transmission'             => $voiture->getTransmission(),
                'couleur'                  => $voiture->getCouleur(),
                'places'                   => $voiture->getPlaces(),
                'portes'                   => $voiture->getPortes(),
                'puissanceCv'              => $voiture->getPuissanceCv(),
                'categorie'                => $voiture->getCategorie(),
                'climatisation'            => $voiture->isClimatisation(),
                'kilometrageActuel'        => $voiture->getKilometrageActuel(),
                'prixJour'                 => $voiture->getPrixJour(),
                'prixSemaine'              => $voiture->getPrixSemaine(),
                'prixMois'                 => $voiture->getPrixMois(),
                'prixAchat'                => $voiture->getPrixAchat(),
                'dateAchat'                => $voiture->getDateAchat()?->format('Y-m-d'),
                'dateExpirationAssurance'  => $voiture->getDateExpirationAssurance()?->format('Y-m-d'),
                'dateExpirationVignette'   => $voiture->getDateExpirationVignette()?->format('Y-m-d'),
                'dateExpirationVisite'     => $voiture->getDateExpirationVisite()?->format('Y-m-d'),
                'voitureStatus'            => $voiture->getVoitureStatus(),
                'effectiveStatus'          => $this->statusService->computeStatus($voiture),
                'bureau'                   => $voiture->getBureau()?->getId(),
                'creePar'                  => $voiture->getCreePar()?->getId(),
                'creeAu'                   => $voiture->getCreeAu()?->format('Y-m-d H:i:s'),
                'editAu'                   => $voiture->getEditAu()?->format('Y-m-d H:i:s'),
                'image'                    => $voiture->getImagePath(),
                'images'                   => array_values(array_filter(array_map(
                    fn($img) => $img->getImagePath(),
                    $voiture->getImages()->toArray()
                ))),
                'galleryImages'            => array_values(array_map(
                    fn($img) => ['id' => $img->getId(), 'path' => $img->getImagePath()],
                    $voiture->getImages()->toArray()
                )),
                'compliance'               => $this->complianceService->getComplianceStatus($voiture),
                'oilChange'                => $this->oilChangeService->getOilStatus($voiture),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        BureauRepository $bureauRepo
    ): JsonResponse {
        try {
            $voiture = new Voiture();

            // 1. Get data from FormData
            $voiture->setMarque($request->request->get('marque'));
            $voiture->setModele($request->request->get('modele'));
            $voiture->setVersion($request->request->get('version'));
            $voiture->setAnnee((int) $request->request->get('annee'));
            $voiture->setImmatriculation($request->request->get('immatriculation'));
            $voiture->setVin($request->request->get('vin'));
            $voiture->setTypeCarburant($request->request->get('typeCarburant', 'Essence'));
            $voiture->setTransmission($request->request->get('transmission'));
            $voiture->setCouleur($request->request->get('couleur'));
            $voiture->setPlaces($request->request->get('places') !== null ? (int) $request->request->get('places') : null);
            $voiture->setPortes($request->request->get('portes') !== null ? (int) $request->request->get('portes') : null);
            $voiture->setPuissanceCv($request->request->get('puissanceCv') !== null && $request->request->get('puissanceCv') !== '' ? (int) $request->request->get('puissanceCv') : null);
            $voiture->setCategorie($request->request->get('categorie'));
            $voiture->setKilometrageActuel((int) $request->request->get('kilometrageActuel', 0));

            $climatisation = $request->request->get('climatisation');
            $voiture->setClimatisation($climatisation === 'true' || $climatisation === '1' || $climatisation === true);

            $voiture->setPrixJour($request->request->get('prixJour', 0));
            $voiture->setPrixSemaine($request->request->get('prixSemaine') ?: null);
            $voiture->setPrixMois($request->request->get('prixMois') ?: null);
            $voiture->setPrixAchat($request->request->get('prixAchat') ?: null);

            $toDate = fn(?string $s) => $s ? new \DateTimeImmutable($s) : null;
            $voiture->setDateAchat($toDate($request->request->get('dateAchat')));
            $voiture->setDateExpirationAssurance($toDate($request->request->get('dateExpirationAssurance')));
            $voiture->setDateExpirationVignette($toDate($request->request->get('dateExpirationVignette')));
            $voiture->setDateExpirationVisite($toDate($request->request->get('dateExpirationVisite')));

            $voiture->setVoitureStatus($request->request->get('voitureStatus', 'brouillon'));
            $voiture->setReservationStatus($request->request->get('reservationStatus', 'confirmed'));
            
            $voiture->setCreeAu(new \DateTimeImmutable());
            $voiture->setCreePar($this->getUser());

            // 2. SET BUREAU - from user or from request
            $user = $this->getUser();

            if ($user->getBureau()) {
                $voiture->setBureau($user->getBureau());
            } elseif ($managerBureau = $bureauRepo->findOneBy(['manager' => $user])) {
                $voiture->setBureau($managerBureau);
            } elseif ($request->request->get('bureauId')) {
                $bureau = $bureauRepo->find((int) $request->request->get('bureauId'));
                if (!$bureau) {
                    return $this->json(['error' => 'Bureau introuvable'], 404);
                }
                $voiture->setBureau($bureau);
            } else {
                return $this->json([
                    'error' => 'bureauId requis',
                    'message' => 'Votre compte n\'est pas associé à un bureau. Envoyez bureauId dans la requête.'
                ], 400);
            }

            // 3. HANDLE THE IMAGE UPLOAD
            $file = $request->files->get('imageFile');
            if ($file) {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    return $this->json(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou WEBP.'], 415);
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return $this->json(['error' => 'Image trop grande. Maximum 5 MB.'], 413);
                }
                $voiture->setImageFile($file);
            }

            // 4. Save main record
            $em->persist($voiture);
            $em->flush();

            $this->activityLog->logCreate('Voiture', $voiture->getId(), $this->snapshotVoiture($voiture), $voiture->getBureau());

            // 5. Handle additional images
            $additionalFiles = $request->files->get('additionalImages', []);
            if (!is_array($additionalFiles)) $additionalFiles = [$additionalFiles];
            $position = 0;
            foreach ($additionalFiles as $addFile) {
                if (!$addFile) continue;
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($addFile->getMimeType(), $allowedMimes)) continue;
                if ($addFile->getSize() > 5 * 1024 * 1024) continue;
                $img = new VoitureImage();
                $img->setVoiture($voiture);
                $img->setImageFile($addFile);
                $img->setPosition($position++);
                $img->setCreeAu(new \DateTimeImmutable());
                $em->persist($img);
            }
            if ($position > 0) $em->flush();

            // 6. Return Response
            return $this->json([
                'message' => 'Voiture créée avec succès',
                'id' => $voiture->getId(),
                'image' => $voiture->getImagePath()
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Voiture $voiture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data    = json_decode($request->getContent(), true);
            $oldSnap = $this->snapshotVoiture($voiture);

            if (isset($data['marque']))                   $voiture->setMarque($data['marque']);
            if (isset($data['modele']))                   $voiture->setModele($data['modele']);
            if (array_key_exists('version', $data))       $voiture->setVersion($data['version']);
            if (isset($data['annee']))                    $voiture->setAnnee((int) $data['annee']);
            if (array_key_exists('immatriculation', $data)) $voiture->setImmatriculation($data['immatriculation']);
            if (array_key_exists('vin', $data))           $voiture->setVin($data['vin']);
            if (isset($data['typeCarburant']))            $voiture->setTypeCarburant($data['typeCarburant']);
            if (array_key_exists('transmission', $data))  $voiture->setTransmission($data['transmission']);
            if (array_key_exists('couleur', $data))       $voiture->setCouleur($data['couleur']);
            if (array_key_exists('places', $data))        $voiture->setPlaces($data['places'] !== null ? (int) $data['places'] : null);
            if (array_key_exists('portes', $data))        $voiture->setPortes($data['portes'] !== null ? (int) $data['portes'] : null);
            if (array_key_exists('puissanceCv', $data))   $voiture->setPuissanceCv($data['puissanceCv'] !== null && $data['puissanceCv'] !== '' ? (int) $data['puissanceCv'] : null);
            if (array_key_exists('categorie', $data))     $voiture->setCategorie($data['categorie']);
            if (isset($data['kilometrageActuel']))        $voiture->setKilometrageActuel((int) $data['kilometrageActuel']);
            if (isset($data['climatisation']))            $voiture->setClimatisation((bool) $data['climatisation']);
            if (isset($data['prixJour']))                 $voiture->setPrixJour($data['prixJour']);
            if (array_key_exists('prixSemaine', $data))   $voiture->setPrixSemaine($data['prixSemaine'] ?: null);
            if (array_key_exists('prixMois', $data))      $voiture->setPrixMois($data['prixMois'] ?: null);
            if (array_key_exists('prixAchat', $data))     $voiture->setPrixAchat($data['prixAchat'] ?: null);
            $toDate = fn(?string $s) => $s ? new \DateTimeImmutable($s) : null;
            if (array_key_exists('dateAchat', $data))               $voiture->setDateAchat($toDate($data['dateAchat']));
            if (array_key_exists('dateExpirationAssurance', $data))  $voiture->setDateExpirationAssurance($toDate($data['dateExpirationAssurance']));
            if (array_key_exists('dateExpirationVignette', $data))   $voiture->setDateExpirationVignette($toDate($data['dateExpirationVignette']));
            if (array_key_exists('dateExpirationVisite', $data))     $voiture->setDateExpirationVisite($toDate($data['dateExpirationVisite']));
            if (isset($data['voitureStatus']))            $voiture->setVoitureStatus($data['voitureStatus']);
            if (isset($data['reservationStatus']))        $voiture->setReservationStatus($data['reservationStatus']);
            $voiture->setEditAu(new \DateTimeImmutable());

            $this->guard->activate();
            try {
                $em->flush();
            } finally {
                $this->guard->deactivate();
            }

            $this->activityLog->logUpdate('Voiture', $voiture->getId(), $oldSnap, $this->snapshotVoiture($voiture), $voiture->getBureau());
            $this->statusService->syncStatus($voiture);

            return $this->json(['message' => 'Voiture mise à jour']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to update vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{id}/images', name: 'add_image', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addImage(Voiture $voiture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $file = $request->files->get('imageFile');
            if (!$file) {
                return $this->json(['error' => 'No image provided'], 400);
            }
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->json(['error' => 'Type de fichier non autorisé.'], 415);
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->json(['error' => 'Image trop grande. Maximum 5 MB.'], 413);
            }
            $position = $voiture->getImages()->count();
            $img = new VoitureImage();
            $img->setVoiture($voiture);
            $img->setImageFile($file);
            $img->setPosition($position);
            $img->setCreeAu(new \DateTimeImmutable());
            $em->persist($img);
            $em->flush();
            return $this->json(['id' => $img->getId(), 'image' => $img->getImagePath()], 201);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to add image',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{voitureId}/images/{imageId}', name: 'delete_image', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteImage(int $voitureId, int $imageId, EntityManagerInterface $em): JsonResponse
    {
        try {
            $img = $em->find(VoitureImage::class, $imageId);
            if (!$img || $img->getVoiture()?->getId() !== $voitureId) {
                return $this->json(['error' => 'Image not found'], 404);
            }
            $em->remove($img);
            $em->flush();
            return $this->json(['message' => 'Image supprimée']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete image',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{id}/image', name: 'update_image', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateImage(Voiture $voiture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $file = $request->files->get('imageFile');
            if (!$file) {
                return $this->json(['error' => 'No image provided'], 400);
            }
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->json(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou WEBP.'], 415);
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->json(['error' => 'Image trop grande. Maximum 5 MB.'], 413);
            }
            $voiture->setImageFile($file);
            $voiture->setEditAu(new \DateTimeImmutable());
            $em->flush();
            return $this->json(['image' => $voiture->getImagePath()]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to update image',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{id}/financial-summary', name: 'financial_summary', methods: ['GET'])]
    public function financialSummary(Voiture $voiture, EntityManagerInterface $em): JsonResponse
    {
        try {
            $reservations = $em->createQueryBuilder()
                ->select('r')->from(Reservation::class, 'r')
                ->where('r.voiture = :v')->andWhere('r.deletedAt IS NULL')
                ->setParameter('v', $voiture)->getQuery()->getResult();

            $totalIncome = array_sum(array_map(fn($r) => (float)($r->getTotal() ?? 0), $reservations));

            $depenses = $em->createQueryBuilder()
                ->select('d')->from(Depense::class, 'd')
                ->where('d.voiture = :v')->andWhere('d.deletedAt IS NULL')
                ->setParameter('v', $voiture)->getQuery()->getResult();

            $totalExpenses = array_sum(array_map(fn($d) => (float)($d->getMontant() ?? 0), $depenses));
            $netProfit = $totalIncome - $totalExpenses;

            $dateAchat = $voiture->getDateAchat();
            $today = new \DateTimeImmutable();
            $daysSince = $dateAchat ? max(1, $today->diff($dateAchat)->days) : 365;
            $rentedDays = array_sum(array_map(
                fn($r) => ($r->getDateDebut() && $r->getDateFin()) ? max(1, $r->getDateFin()->diff($r->getDateDebut())->days) : 0,
                $reservations
            ));
            $occupancyRate = round(min(100, $rentedDays / $daysSince * 100), 1);

            $breakdownMap = [];
            foreach ($depenses as $dep) {
                $type = $dep->getTypeDepense() ?? 'autre';
                if (!isset($breakdownMap[$type])) $breakdownMap[$type] = ['category' => $type, 'total' => 0.0, 'count' => 0];
                $breakdownMap[$type]['total'] = round($breakdownMap[$type]['total'] + (float)($dep->getMontant() ?? 0), 2);
                $breakdownMap[$type]['count']++;
            }

            $monthly = [];
            for ($i = 5; $i >= 0; $i--) {
                $d = $today->modify("-$i months");
                $key = $d->format('Y-m');
                $monthly[$key] = ['month' => $d->format('M Y'), 'key' => $key, 'income' => 0.0, 'expenses' => 0.0];
            }
            foreach ($reservations as $r) {
                $key = $r->getDateDebut()?->format('Y-m');
                if ($key && isset($monthly[$key])) $monthly[$key]['income'] = round($monthly[$key]['income'] + (float)($r->getTotal() ?? 0), 2);
            }
            foreach ($depenses as $d) {
                $key = $d->getDateDebut()?->format('Y-m');
                if ($key && isset($monthly[$key])) $monthly[$key]['expenses'] = round($monthly[$key]['expenses'] + (float)($d->getMontant() ?? 0), 2);
            }

            return $this->json([
                'totalIncome'   => round($totalIncome, 2),
                'totalExpenses' => round($totalExpenses, 2),
                'netProfit'     => round($netProfit, 2),
                'occupancyRate' => $occupancyRate,
                'totalRentals'  => count($reservations),
                'breakdown'     => array_values($breakdownMap),
                'monthly'       => array_values($monthly),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to fetch financial summary', 'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Erreur interne.'], 500);
        }
    }

    #[Route('/{id}/transactions', name: 'transactions', methods: ['GET'])]
    public function transactions(Voiture $voiture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $typeF   = $request->query->get('type', '');
            $fromF   = $request->query->get('from', '');
            $toF     = $request->query->get('to', '');
            $statusF = $request->query->get('status', '');

            // Frontend sends specific category values (FIN_TX_TYPES), not a generic
            // income/expense split. Map each category to: whether it's reservations,
            // and which Depense.typeDepense value it corresponds to.
            $depenseTypeMap = [
                'reparation'     => 'reparation',
                'assurance'      => 'assurance',
                'vidange'        => 'vidange',
                'vignette'       => 'vignette',
                'suivitechnique' => 'suivi_technique',
                'adblue'         => 'adblue',
            ];
            $includeReservations = !$typeF || $typeF === 'reservation';
            $includeDepenses     = !$typeF || isset($depenseTypeMap[$typeF]);
            $depenseTypeFilter   = $depenseTypeMap[$typeF] ?? null;

            // Status filter uses generic paid/pending/cancelled buckets (the pills),
            // but rows carry raw, source-specific status strings (reservationStatus in
            // French, or Depense's StatusEnum). Normalize both sides before comparing —
            // mirrors FinancialTabComponent.txStatusClass()'s grouping, extended to
            // also cover impaye/partiel (otherwise unpaid/partial expenses matched
            // nothing under "Pending").
            $normalizeStatus = function (string $raw): string {
                $s = strtolower($raw);
                if (in_array($s, ['payee', 'paid', 'confirmed', 'confirmee', 'terminee', 'completed'], true)) return 'paid';
                if (in_array($s, ['pending', 'en_attente', 'impaye', 'partiel'], true)) return 'pending';
                if (in_array($s, ['annulee', 'cancelled', 'annule'], true)) return 'cancelled';
                return 'other';
            };

            $rows = [];

            if ($includeReservations) {
                $reservations = $em->createQueryBuilder()->select('r')->from(Reservation::class, 'r')
                    ->where('r.voiture = :v')->andWhere('r.deletedAt IS NULL')
                    ->setParameter('v', $voiture)->getQuery()->getResult();
                foreach ($reservations as $r) {
                    $date = $r->getDateDebut()?->format('Y-m-d');
                    $status = $r->getReservationStatus() ?? '';
                    if ($fromF && $date && $date < $fromF) continue;
                    if ($toF   && $date && $date > $toF)   continue;
                    if ($statusF && $normalizeStatus($status) !== $statusF) continue;
                    $rows[] = [
                        'date'              => $date,
                        'type'              => 'income',
                        'direction'         => 'income',
                        'category'          => 'reservation',
                        'description'       => 'Réservation #' . $r->getId(),
                        'amount'            => (float)($r->getTotal() ?? 0),
                        'status'            => $status,
                        'reservationStatus' => $status,
                        'paymentStatus'     => $r->getPaymentStatus(),
                    ];
                }
            }

            if ($includeDepenses) {
                $depenses = $em->createQueryBuilder()->select('d')->from(Depense::class, 'd')
                    ->where('d.voiture = :v')->andWhere('d.deletedAt IS NULL')
                    ->setParameter('v', $voiture)->getQuery()->getResult();

                // Facture file lives on the type-specific wrapper entity, not on Depense
                // itself — batch-fetch depenseId => filePath per wrapper type to avoid N+1.
                $depenseIds = array_map(fn($d) => $d->getId(), $depenses);
                $filePathByDepenseId = [];
                if ($depenseIds) {
                    foreach ([Assurance::class, Vignette::class, SuiviTechnique::class, Vidange::class] as $wrapperClass) {
                        $wrapperRows = $em->createQueryBuilder()
                            ->select('IDENTITY(w.depense) as depenseId, w.filePath')
                            ->from($wrapperClass, 'w')
                            ->where('w.depense IN (:ids)')->andWhere('w.filePath IS NOT NULL')
                            ->setParameter('ids', $depenseIds)
                            ->getQuery()->getArrayResult();
                        foreach ($wrapperRows as $wr) {
                            $filePathByDepenseId[(int) $wr['depenseId']] = $wr['filePath'];
                        }
                    }
                }

                foreach ($depenses as $d) {
                    if ($depenseTypeFilter && $d->getTypeDepense() !== $depenseTypeFilter) continue;
                    $date = $d->getDateDebut()?->format('Y-m-d');
                    $status = $d->getStatut()?->value ?? '';
                    if ($fromF   && $date && $date < $fromF)  continue;
                    if ($toF     && $date && $date > $toF)    continue;
                    if ($statusF && $normalizeStatus($status) !== $statusF) continue;
                    $rows[] = [
                        'date'        => $date,
                        'type'        => 'expense',
                        'direction'   => 'expense',
                        'category'    => $d->getTypeDepense() ?? 'autre',
                        'description' => $d->getDescription() ?: ($d->getTypeDepense() ?? 'Dépense'),
                        'amount'      => (float)($d->getMontant() ?? 0),
                        'status'      => $status,
                        'filePath'    => $filePathByDepenseId[$d->getId()] ?? null,
                        'depenseId'   => $d->getId(),
                        'montantTotal' => (float)($d->getMontant() ?? 0),
                        'montantPaye'  => (float)($d->getMontantPaye() ?? 0),
                    ];
                }
            }

            usort($rows, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
            return $this->json($rows);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to fetch transactions', 'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Erreur interne.'], 500);
        }
    }

    #[Route('/{id}/lifecycle', name: 'lifecycle', methods: ['POST'])]
    public function lifecycle(Voiture $voiture, Request $request): JsonResponse
    {
        $data      = json_decode($request->getContent(), true) ?? [];
        $eventName = $data['event'] ?? '';

        try {
            /** @var User|null $user */
            $user = $this->getUser();

            if ($eventName === 'vehicle.activated') {
                $event = new TemporalSyncTriggered(
                    $voiture->getId(),
                    $voiture->getBureau()?->getId(),
                );
            } elseif ($eventName === 'vehicle.setup_started') {
                $event = new VehicleSetupStarted($voiture->getId());
            } elseif ($eventName === 'vehicle.decommission_initiated') {
                $this->flm->assertCanInitiateDecommission($voiture);
                $event = new DecommissionInitiated(
                    $voiture->getId(),
                    $user instanceof User ? $user->getId() : 0,
                    $data['reason'] ?? null,
                );
            } else {
                return $this->json(['error' => 'Unknown lifecycle event: ' . $eventName], 400);
            }

            $result = $this->flm->applyEvent($voiture, $event);

            return $this->json([
                'previousState' => $result->previousState->value,
                'currentState'  => $result->currentState->value,
                'stateChanged'  => $result->stateChanged,
                'warnings'      => $result->warnings,
                'effects'       => $result->postEffects,
            ]);
        } catch (LifecycleViolationException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (InvalidTransitionException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            return $this->json([
                'error'   => 'Lifecycle event failed',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Voiture $voiture, EntityManagerInterface $em): JsonResponse
    {
        try {
            $snap = $this->snapshotVoiture($voiture);
            $bureau = $voiture->getBureau();
            $voiture->setDeletedAt(new \DateTimeImmutable());
            $em->flush();

            $this->activityLog->logDelete('Voiture', $voiture->getId(), $snap, $bureau);

            return $this->json(['message' => 'Voiture supprimée'], 200);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/sync-statuses', name: 'sync_statuses', methods: ['POST'])]
    public function syncStatuses(): JsonResponse
    {
        $changed = $this->statusService->syncAll();
        return $this->json(['message' => "Status synced. $changed car(s) updated.", 'changed' => $changed]);
    }
}