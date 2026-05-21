<?php

namespace App\Controller\Api;

use App\Entity\Voiture;
use App\Repository\VoitureRepository;
use App\Repository\BureauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/voiture', name: 'app_api_voiture_')]
class VoitureController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, VoitureRepository $repo): JsonResponse
    {
        // 1. Get query parameters
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $search = $request->query->get('search', '');
        $voitureStatus = $request->query->get('voitureStatus', '');
        $typeCarburant = $request->query->get('typeCarburant', '');
        $sort = $request->query->get('sort', 'id');
        $direction = $request->query->get('direction', 'ASC');

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

        // 4. Format response
        $data = array_map(fn($v) => [
            'id'                => $v->getId(),
            'marque'            => $v->getMarque(),
            'modele'            => $v->getModele(),
            'annee'             => $v->getAnnee(),
            'kilometrageActuel' => $v->getKilometrageActuel(),
            'typeCarburant'     => $v->getTypeCarburant(),
            'couleur'           => $v->getCouleur(),
            'climatisation'     => $v->isClimatisation(),
            'prixJour'          => $v->getPrixJour(),
            'voitureStatus'     => $v->getVoitureStatus(),
            'reservationStatus' => $v->getReservationStatus(),
            'bureau'            => $v->getBureau()?->getId(),
            'image' => $v->getImagePath(), 
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
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Voiture $voiture): JsonResponse
    {
        return $this->json([
            'id'                => $voiture->getId(),
            'marque'            => $voiture->getMarque(),
            'modele'            => $voiture->getModele(),
            'annee'             => $voiture->getAnnee(),
            'kilometrageActuel' => $voiture->getKilometrageActuel(),
            'typeCarburant'     => $voiture->getTypeCarburant(),
            'couleur'           => $voiture->getCouleur(),
            'climatisation'     => $voiture->isClimatisation(),
            'prixJour'          => $voiture->getPrixJour(),
            'prixAchat'         => $voiture->getPrixAchat(),
            'voitureStatus'     => $voiture->getVoitureStatus(),
            'reservationStatus' => $voiture->getReservationStatus(),
            'bureau'            => $voiture->getBureau()?->getId(),
            'creePar'           => $voiture->getCreePar()?->getId(),
            'creeAu'            => $voiture->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'            => $voiture->getEditAu()?->format('Y-m-d H:i:s'),
            'image' => $v->getImagePath(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        BureauRepository $bureauRepo
    ): JsonResponse {
        $voiture = new Voiture();

        // 1. Get data from FormData (Not JSON)
        // We access fields directly from $request->request
        $voiture->setMarque($request->request->get('marque'));
        $voiture->setModele($request->request->get('modele'));
        $voiture->setAnnee($request->request->get('annee'));
        
        // Cast to int for safety
        $voiture->setKilometrageActuel((int) $request->request->get('kilometrageActuel', 0));
        
        $voiture->setTypeCarburant($request->request->get('typeCarburant'));
        $voiture->setCouleur($request->request->get('couleur'));

        // Handle Boolean (Checkbox)
        $climatisation = $request->request->get('climatisation');
        $voiture->setClimatisation($climatisation === 'true' || $climatisation === 1 || $climatisation === true);

        $voiture->setPrixJour($request->request->get('prixJour'));
        $voiture->setPrixAchat($request->request->get('prixAchat'));
        $voiture->setVoitureStatus($request->request->get('voitureStatus', 'available'));
        $voiture->setReservationStatus($request->request->get('reservationStatus', 'confirmed'));
        
        $voiture->setCreeAu(new \DateTimeImmutable());
        $voiture->setCreePar($this->getUser());

        // Bureau Logic
        if ($request->request->get('bureauId')) {
            $bureau = $bureauRepo->find($request->request->get('bureauId'));
            if ($bureau) {
                $voiture->setBureau($bureau);
            }
        }

        // 2. HANDLE THE IMAGE UPLOAD
        // We use $request->files->get() to get the uploaded file
        $file = $request->files->get('imageFile');

        if ($file) {
            // VICH UPLOADER MAGIC:
            // This triggers the event listener to move the file
            $voiture->setImageFile($file);
        }

        // 3. Save
        $em->persist($voiture);
        $em->flush();

        // 4. Return Response
        return $this->json([
            'message' => 'Voiture créée avec succès',
            'id' => $voiture->getId(),
            // Return the image path so Angular can display it
            'image' => $voiture->getImagePath() 
        ], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Voiture $voiture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['marque']))            $voiture->setMarque($data['marque']);
        if (isset($data['modele']))            $voiture->setModele($data['modele']);
        if (isset($data['annee']))             $voiture->setAnnee($data['annee']);
        if (isset($data['kilometrageActuel'])) $voiture->setKilometrageActuel($data['kilometrageActuel']);
        if (isset($data['typeCarburant']))     $voiture->setTypeCarburant($data['typeCarburant']);
        if (isset($data['couleur']))           $voiture->setCouleur($data['couleur']);
        if (isset($data['climatisation']))     $voiture->setClimatisation($data['climatisation']);
        if (isset($data['prixJour']))          $voiture->setPrixJour($data['prixJour']);
        if (isset($data['voitureStatus']))     $voiture->setVoitureStatus($data['voitureStatus']);
        if (isset($data['reservationStatus'])) $voiture->setReservationStatus($data['reservationStatus']);
        $voiture->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Voiture mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Voiture $voiture, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($voiture);
        $em->flush();

        return $this->json(['message' => 'Voiture supprimée'], 204);
    }
}