<?php

namespace App\Controller\Api;

use App\Entity\Paiement;
use App\Repository\PaiementRepository;
use App\Repository\CreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/paiement', name: 'app_api_paiement_')]
class PaiementController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(PaiementRepository $repo): JsonResponse
    {
        $data = array_map(fn($p) => [
            'id'           => $p->getId(),
            'montant'      => $p->getMontant(),
            'datePaiement' => $p->getDatePaiement()?->format('Y-m-d'),
            'statut'       => $p->getStatut(),
            'credit'       => $p->getCredit()?->getId(),
            'creePar'      => $p->getCreePar()?->getId(),
        ], $repo->findAll());

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Paiement $paiement): JsonResponse
    {
        return $this->json([
            'id'           => $paiement->getId(),
            'montant'      => $paiement->getMontant(),
            'datePaiement' => $paiement->getDatePaiement()?->format('Y-m-d'),
            'statut'       => $paiement->getStatut(),
            'credit'       => $paiement->getCredit()?->getId(),
            'creePar'      => $paiement->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CreditRepository $creditRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $paiement = new Paiement();
        $paiement->setMontant($data['montant']);
        $paiement->setDatePaiement(new \DateTimeImmutable($data['datePaiement']));
        $paiement->setStatut($data['statut'] ?? 'payé');
        $paiement->setCreeAu(new \DateTimeImmutable());
        $paiement->setCreePar($this->getUser());

        if (isset($data['creditId'])) {
            $credit = $creditRepo->find($data['creditId']);
            if ($credit) $paiement->setCredit($credit);
        }

        $em->persist($paiement);
        $em->flush();

        return $this->json(['message' => 'Paiement créé', 'id' => $paiement->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Paiement $paiement, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['montant']))      $paiement->setMontant($data['montant']);
        if (isset($data['datePaiement'])) $paiement->setDatePaiement(new \DateTimeImmutable($data['datePaiement']));
        if (isset($data['statut']))       $paiement->setStatut($data['statut']);

        $em->flush();

        return $this->json(['message' => 'Paiement mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Paiement $paiement, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($paiement);
        $em->flush();

        return $this->json(['message' => 'Paiement supprimé'], 204);
    }
}