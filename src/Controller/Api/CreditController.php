<?php

namespace App\Controller\Api;

use App\Entity\Credit;
use App\Repository\CreditRepository;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/credit', name: 'app_api_credit_')]
class CreditController extends AbstractController
{
    private function serialize(Credit $c, bool $withPaiements = false): array
    {
        $voit = $c->getVoiture();
        $data = [
            'id'           => $c->getId(),
            'montantTotal' => $c->getMontantTotal(),
            'mensualite'   => $c->getMensualite(),
            'dateDebut'    => $c->getDateDebut()?->format('Y-m-d'),
            'dateFin'      => $c->getDateFin()?->format('Y-m-d'),
            'dureeMois'    => $c->getDureeMois(),
            'statut'       => $c->getStatut(),
            'voitureId'    => $voit?->getId(),
            'voiture'      => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'creeAu'       => $c->getCreeAu()?->format('Y-m-d'),
        ];
        if ($withPaiements) {
            $data['paiements'] = $c->getPaiements()->map(fn($p) => [
                'id'           => $p->getId(),
                'montant'      => $p->getMontant(),
                'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
                'statut'       => $p->getStatut(),
            ])->toArray();
        }
        return $data;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(CreditRepository $repo): JsonResponse
    {
        return $this->json(array_map(fn($c) => $this->serialize($c), $repo->findAll()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Credit $credit): JsonResponse
    {
        return $this->json($this->serialize($credit, true));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $credit = new Credit();
        $credit->setMontantTotal($data['montantTotal']);
        $credit->setMensualite($data['mensualite']);
        $credit->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        $credit->setDateFin(new \DateTimeImmutable($data['dateFin']));
        $credit->setDureeMois($data['dureeMois']);
        $credit->setStatut($data['statut'] ?? 'en_cours');
        $credit->setCreeAu(new \DateTimeImmutable());
        $credit->setCreePar($this->getUser());

        if (isset($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
            if ($voiture) $credit->setVoiture($voiture);
        }

        $em->persist($credit);
        $em->flush();

        return $this->json(['message' => 'Crédit créé', 'id' => $credit->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Credit $credit, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['montantTotal'])) $credit->setMontantTotal($data['montantTotal']);
        if (isset($data['mensualite']))   $credit->setMensualite($data['mensualite']);
        if (isset($data['dateDebut']))    $credit->setDateDebut(new \DateTimeImmutable($data['dateDebut']));
        if (isset($data['dateFin']))      $credit->setDateFin(new \DateTimeImmutable($data['dateFin']));
        if (isset($data['dureeMois']))    $credit->setDureeMois($data['dureeMois']);
        if (isset($data['statut']))       $credit->setStatut($data['statut']);
        $credit->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Crédit mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Credit $credit, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($credit);
        $em->flush();

        return $this->json(['message' => 'Crédit supprimé'], 204);
    }
}