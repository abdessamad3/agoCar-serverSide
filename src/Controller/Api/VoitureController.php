<?php

namespace App\Controller\Api;

use App\Entity\Voiture;
use App\Entity\VoitureImage;
use App\Repository\VoitureRepository;
use App\Repository\BureauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;

#[Route('/api/voiture', name: 'app_api_voiture_')]
#[IsGranted('ROLE_USER')]
class VoitureController extends AbstractController
{
    public function __construct(private KernelInterface $kernel) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, VoitureRepository $repo): JsonResponse
    {
        try {
            // 1. Get query parameters
            $page = (int) $request->query->get('page', 1);
            $limit = (int) $request->query->get('limit', 10);
            $search = $request->query->get('search', '');
            $voitureStatus = $request->query->get('voitureStatus', '');
            $typeCarburant = $request->query->get('typeCarburant', '');
            $sort = $request->query->get('sort', 'id');
            $direction = $request->query->get('direction', 'ASC');
            $dateDebutStr = $request->query->get('dateDebut', '');
            $dateFinStr   = $request->query->get('dateFin', '');

            // Validate pagination
            $page = max(1, $page);
            $limit = min(100, max(1, $limit));
            $direction = in_array(strtoupper($direction), ['ASC', 'DESC']) ? strtoupper($direction) : 'ASC';

            // 2. Build filters array
            $filters = [
                'search' => $search,
                'voitureStatus' => $voitureStatus,
                'typeCarburant' => $typeCarburant,
                'sort' => $sort,
                'direction' => $direction,
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

            // 4. Format response
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
                'caution'                  => $v->getCaution(),
                'dateAchat'                => $v->getDateAchat()?->format('Y-m-d'),
                'dateExpirationAssurance'  => $v->getDateExpirationAssurance()?->format('Y-m-d'),
                'dateExpirationVignette'   => $v->getDateExpirationVignette()?->format('Y-m-d'),
                'dateExpirationVisite'     => $v->getDateExpirationVisite()?->format('Y-m-d'),
                'voitureStatus'            => $v->getVoitureStatus(),
                'bureau'                   => $v->getBureau()?->getId(),
                'image'                    => $v->getImagePath(),
                'galleryImages'            => array_values(array_map(
                    fn($img) => ['id' => $img->getId(), 'path' => $img->getImagePath()],
                    $v->getImages()->toArray()
                )),
                'availabilityForPeriod'    => $datesActive
                    ? (in_array($v->getId(), $bookedIds) ? 'reservee' : 'disponible')
                    : null,
            ], $voitures);

            return $this->json([
                'data' => $data,
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int) $pages,
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
                'caution'                  => $voiture->getCaution(),
                'dateAchat'                => $voiture->getDateAchat()?->format('Y-m-d'),
                'dateExpirationAssurance'  => $voiture->getDateExpirationAssurance()?->format('Y-m-d'),
                'dateExpirationVignette'   => $voiture->getDateExpirationVignette()?->format('Y-m-d'),
                'dateExpirationVisite'     => $voiture->getDateExpirationVisite()?->format('Y-m-d'),
                'voitureStatus'            => $voiture->getVoitureStatus(),
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
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to fetch vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
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
            $voiture->setCaution($request->request->get('caution') ?: null);

            $toDate = fn(?string $s) => $s ? new \DateTimeImmutable($s) : null;
            $voiture->setDateAchat($toDate($request->request->get('dateAchat')));
            $voiture->setDateExpirationAssurance($toDate($request->request->get('dateExpirationAssurance')));
            $voiture->setDateExpirationVignette($toDate($request->request->get('dateExpirationVignette')));
            $voiture->setDateExpirationVisite($toDate($request->request->get('dateExpirationVisite')));

            $voiture->setVoitureStatus($request->request->get('voitureStatus', 'disponible'));
            $voiture->setReservationStatus($request->request->get('reservationStatus', 'confirmed'));
            
            $voiture->setCreeAu(new \DateTimeImmutable());
            $voiture->setCreePar($this->getUser());

            // 2. SET BUREAU - from user or from request
            $user = $this->getUser();

            if ($user->getBureau()) {
                $voiture->setBureau($user->getBureau());
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
    public function update(Voiture $voiture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

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
            if (array_key_exists('caution', $data))       $voiture->setCaution($data['caution'] ?: null);
            $toDate = fn(?string $s) => $s ? new \DateTimeImmutable($s) : null;
            if (array_key_exists('dateAchat', $data))               $voiture->setDateAchat($toDate($data['dateAchat']));
            if (array_key_exists('dateExpirationAssurance', $data))  $voiture->setDateExpirationAssurance($toDate($data['dateExpirationAssurance']));
            if (array_key_exists('dateExpirationVignette', $data))   $voiture->setDateExpirationVignette($toDate($data['dateExpirationVignette']));
            if (array_key_exists('dateExpirationVisite', $data))     $voiture->setDateExpirationVisite($toDate($data['dateExpirationVisite']));
            if (isset($data['voitureStatus']))            $voiture->setVoitureStatus($data['voitureStatus']);
            if (isset($data['reservationStatus']))        $voiture->setReservationStatus($data['reservationStatus']);
            $voiture->setEditAu(new \DateTimeImmutable());

            $em->flush();

            return $this->json(['message' => 'Voiture mise à jour']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to update vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    #[Route('/{id}/images', name: 'add_image', methods: ['POST'])]
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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Voiture $voiture, EntityManagerInterface $em): JsonResponse
    {
        try {
            $em->remove($voiture);
            $em->flush();

            return $this->json(['message' => 'Voiture supprimée'], 200);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete vehicle',
                'message' => $this->kernel->isDebug() ? $e->getMessage() : 'Une erreur interne est survenue.'
            ], 500);
        }
    }
}