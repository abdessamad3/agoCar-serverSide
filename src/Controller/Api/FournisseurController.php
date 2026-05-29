<?php

namespace App\Controller\Api;

use App\Entity\Fournisseur;
use App\Repository\FournisseurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/fournisseur', name: 'app_api_fournisseur_')]
class FournisseurController extends AbstractController
{
    private function serialize(Fournisseur $f): array
    {
        return [
            'id'           => $f->getId(),
            'raisonSociale'=> $f->getRaisonSociale(),
            'nom'          => $f->getNom(),
            'prenom'       => $f->getPrenom(),
            'telephone'    => $f->getTelephone(),
            'email'        => $f->getEmail(),
            'adresse'      => $f->getAdresse(),
            'ville'        => $f->getVille(),
            'ice'          => $f->getIce(),
            'infoBancaire' => $f->getInfoBancaire(),
            'notes'        => $f->getNotes(),
            'creeAu'       => $f->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(FournisseurRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($f) => $this->serialize($f), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Fournisseur $fournisseur): JsonResponse
    {
        return $this->json($this->serialize($fournisseur));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $f = new Fournisseur();
        $f->setRaisonSociale($data['raisonSociale'] ?? 'Sans nom');
        $f->setNom($data['nom'] ?? null);
        $f->setPrenom($data['prenom'] ?? null);
        $f->setTelephone($data['telephone'] ?? null);
        $f->setEmail($data['email'] ?? null);
        $f->setAdresse($data['adresse'] ?? null);
        $f->setVille($data['ville'] ?? null);
        $f->setIce($data['ice'] ?? null);
        $f->setInfoBancaire($data['infoBancaire'] ?? null);
        $f->setNotes($data['notes'] ?? null);
        $f->setCreeAu(new \DateTimeImmutable());

        $em->persist($f);
        $em->flush();

        return $this->json(['message' => 'Fournisseur créé', 'id' => $f->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Fournisseur $fournisseur, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['raisonSociale'])) $fournisseur->setRaisonSociale($data['raisonSociale']);
        if (array_key_exists('nom', $data))          $fournisseur->setNom($data['nom']);
        if (array_key_exists('prenom', $data))       $fournisseur->setPrenom($data['prenom']);
        if (array_key_exists('telephone', $data))    $fournisseur->setTelephone($data['telephone']);
        if (array_key_exists('email', $data))        $fournisseur->setEmail($data['email']);
        if (array_key_exists('adresse', $data))      $fournisseur->setAdresse($data['adresse']);
        if (array_key_exists('ville', $data))        $fournisseur->setVille($data['ville']);
        if (array_key_exists('ice', $data))          $fournisseur->setIce($data['ice']);
        if (array_key_exists('infoBancaire', $data)) $fournisseur->setInfoBancaire($data['infoBancaire']);
        if (array_key_exists('notes', $data))        $fournisseur->setNotes($data['notes']);
        $fournisseur->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Fournisseur mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Fournisseur $fournisseur, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($fournisseur);
        $em->flush();

        return $this->json(['message' => 'Fournisseur supprimé'], 204);
    }
}
