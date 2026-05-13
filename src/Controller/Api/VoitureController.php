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
final class VoitureController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(VoitureRepository $repo): JsonResponse
    {
        $voitures = $repo->findAll();
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
        ], $voitures);

        return $this->json($data);
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
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        BureauRepository $bureauRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $voiture = new Voiture();
        $voiture->setMarque($data['marque']);
        $voiture->setModele($data['modele']);
        $voiture->setAnnee($data['annee']);
        $voiture->setKilometrageActuel($data['kilometrageActuel'] ?? 0);
        $voiture->setTypeCarburant($data['typeCarburant']);
        $voiture->setCouleur($data['couleur'] ?? null);
        $voiture->setClimatisation($data['climatisation'] ?? false);
        $voiture->setPrixJour($data['prixJour']);
        $voiture->setPrixAchat($data['prixAchat'] ?? null);
        $voiture->setVoitureStatus($data['voitureStatus'] ?? 'available');
        $voiture->setReservationStatus($data['reservationStatus'] ?? 'confirmed');
        $voiture->setCreeAu(new \DateTime());
        $voiture->setCreePar($this->getUser());

        if (isset($data['bureauId'])) {
            $bureau = $bureauRepo->find($data['bureauId']);
            if ($bureau) $voiture->setBureau($bureau);
        }

        $em->persist($voiture);
        $em->flush();

        return $this->json(['message' => 'Voiture créée', 'id' => $voiture->getId()], 201);
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
        $voiture->setEditAu(new \DateTime());

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
