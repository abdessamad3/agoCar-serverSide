<?php

namespace App\Controller\Api;

use App\Entity\AchatVoiture;
use App\Repository\AchatVoitureRepository;
use App\Repository\VoitureRepository;
use App\Repository\FournisseurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/achat-voiture', name: 'app_api_achat_voiture_')]
class AchatVoitureController extends AbstractController
{
    private function serialize(AchatVoiture $a): array
    {
        $v = $a->getVoiture();
        $f = $a->getFournisseur();
        return [
            'id'                => $a->getId(),
            'voitureId'         => $v?->getId(),
            'voiture'           => trim(($v?->getMarque() ?? '') . ' ' . ($v?->getModele() ?? '')),
            'fournisseurId'     => $f?->getId(),
            'fournisseur'       => $f ? ($f->getRaisonSociale() ?? $f->getNom() ?? '') : null,
            'dateAchat'         => $a->getDateAchat()?->format('Y-m-d'),
            'prixAchat'         => $a->getPrixAchat(),
            'apport'            => $a->getApport(),
            'typeFinancement'   => $a->getTypeFinancement(),
            'mensualite'        => $a->getMensualite(),
            'tauxInteret'       => $a->getTauxInteret(),
            'dateDebutCredit'   => $a->getDateDebutCredit()?->format('Y-m-d'),
            'resteAFinancer'    => $a->getResteAFinancer(),
            'dureeMois'         => $a->getDureeMois(),
            'dernierMensualite' => $a->getDernierMensualite(),
            'statut'            => $a->getStatut(),
            'notes'             => $a->getNotes(),
            'creeAu'            => $a->getCreeAu()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(AchatVoitureRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($a) => $this->serialize($a), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(AchatVoiture $achatVoiture): JsonResponse
    {
        return $this->json($this->serialize($achatVoiture));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        FournisseurRepository $fournisseurRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $a = new AchatVoiture();

        if (!empty($data['voitureId'])) {
            $v = $voitureRepo->find($data['voitureId']);
            if ($v) $a->setVoiture($v);
        }
        if (!empty($data['fournisseurId'])) {
            $f = $fournisseurRepo->find($data['fournisseurId']);
            if ($f) $a->setFournisseur($f);
        }

        $a->setDateAchat(new \DateTimeImmutable($data['dateAchat'] ?? 'now'));
        $a->setPrixAchat($data['prixAchat'] ?? 0);
        $a->setApport($data['apport'] ?? null);
        $a->setTypeFinancement($data['typeFinancement'] ?? 'comptant');
        $a->setMensualite($data['mensualite'] ?? null);
        $a->setTauxInteret($data['tauxInteret'] ?? null);
        $a->setResteAFinancer($data['resteAFinancer'] ?? null);
        $a->setDureeMois($data['dureeMois'] ?? null);
        $a->setDernierMensualite($data['dernierMensualite'] ?? null);
        $a->setStatut($data['statut'] ?? 'actif');
        $a->setNotes($data['notes'] ?? null);
        $a->setCreeAu(new \DateTimeImmutable());

        if (!empty($data['dateDebutCredit'])) {
            $a->setDateDebutCredit(new \DateTimeImmutable($data['dateDebutCredit']));
        }

        $em->persist($a);
        $em->flush();

        return $this->json(['message' => 'Achat créé', 'id' => $a->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        AchatVoiture $achatVoiture,
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        FournisseurRepository $fournisseurRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['voitureId'])) {
            $v = $voitureRepo->find($data['voitureId']);
            $achatVoiture->setVoiture($v ?: null);
        }
        if (array_key_exists('fournisseurId', $data)) {
            $f = $data['fournisseurId'] ? $fournisseurRepo->find($data['fournisseurId']) : null;
            $achatVoiture->setFournisseur($f);
        }
        if (isset($data['dateAchat']))         $achatVoiture->setDateAchat(new \DateTimeImmutable($data['dateAchat']));
        if (isset($data['prixAchat']))         $achatVoiture->setPrixAchat($data['prixAchat']);
        if (array_key_exists('apport', $data)) $achatVoiture->setApport($data['apport']);
        if (isset($data['typeFinancement']))   $achatVoiture->setTypeFinancement($data['typeFinancement']);
        if (array_key_exists('mensualite', $data))        $achatVoiture->setMensualite($data['mensualite']);
        if (array_key_exists('tauxInteret', $data))       $achatVoiture->setTauxInteret($data['tauxInteret']);
        if (array_key_exists('resteAFinancer', $data))    $achatVoiture->setResteAFinancer($data['resteAFinancer']);
        if (array_key_exists('dureeMois', $data))         $achatVoiture->setDureeMois($data['dureeMois']);
        if (array_key_exists('dernierMensualite', $data)) $achatVoiture->setDernierMensualite($data['dernierMensualite']);
        if (isset($data['statut']))            $achatVoiture->setStatut($data['statut']);
        if (array_key_exists('notes', $data))  $achatVoiture->setNotes($data['notes']);
        if (array_key_exists('dateDebutCredit', $data)) {
            $achatVoiture->setDateDebutCredit($data['dateDebutCredit'] ? new \DateTimeImmutable($data['dateDebutCredit']) : null);
        }
        $achatVoiture->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Achat mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(AchatVoiture $achatVoiture, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($achatVoiture);
        $em->flush();

        return $this->json(['message' => 'Achat supprimé'], 204);
    }
}
