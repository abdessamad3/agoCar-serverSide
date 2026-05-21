<?php

namespace App\Controller\Api;

use App\Entity\Depense;
use App\Repository\DepenseRepository;
use App\Repository\VoitureRepository;
use App\Repository\BureauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Enum\StatusEnum;

#[Route('/api/depense', name: 'app_api_depense_')]
class DepenseController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(DepenseRepository $repo): JsonResponse
    {
        $depenses = $repo->findAll();
        $data = array_map(fn($d) => [
            'id'           => $d->getId(),
            'date'         => $d->getDate()?->format('Y-m-d'),
            'typeDepense'  => $d->getTypeDepense(),
            'description'  => $d->getDescription(),
            'montant'      => $d->getMontant(),
            'statut'       => $d->getStatut()?->value,
            'datePaiement' => $d->getDatePaiement()?->format('Y-m-d'),
            'voiture'      => $d->getVoiture()?->getId(),
            'bureau'       => $d->getBureau()?->getId(),
            'creePar'      => $d->getCreePar()?->getId(),
            'creeAu'       => $d->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $depenses);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Depense $depense): JsonResponse
    {
        return $this->json([
            'id'           => $depense->getId(),
            'date'         => $depense->getDate()?->format('Y-m-d'),
            'typeDepense'  => $depense->getTypeDepense(),
            'description'  => $depense->getDescription(),
            'montant'      => $depense->getMontant(),
            'statut'       => $depense->getStatut()?->value,
            'datePaiement' => $depense->getDatePaiement()?->format('Y-m-d'),
            'voiture'      => $depense->getVoiture()?->getId(),
            'bureau'       => $depense->getBureau()?->getId(),
            'creePar'      => $depense->getCreePar()?->getId(),
            'creeAu'       => $depense->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'       => $depense->getEditAu()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VoitureRepository $voitureRepo,
        BureauRepository $bureauRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $depense = new Depense();
        $depense->setDate(new \DateTimeImmutable($data['date']));
        $depense->setTypeDepense($data['typeDepense']);
        $depense->setDescription($data['description'] ?? null);
        $depense->setMontant($data['montant']);
        // $depense->setStatut($data['statut'] ?? 'pending');
        try {
            $statut = isset($data['statut'])
                ? StatusEnum::from($data['statut'])
                : StatusEnum::PENDING;

            $depense->setStatut($statut);

        } catch (\ValueError $e) {

            return $this->json([
                'message' => 'Statut invalide'
            ], 400);
        }
        $depense->setDatePaiement(isset($data['datePaiement']) ? new \DateTimeImmutable($data['datePaiement']) : null);
        $depense->setCreeAu(new \DateTimeImmutable());
        $depense->setCreePar($this->getUser());

        if (isset($data['voitureId'])) {
            $voiture = $voitureRepo->find($data['voitureId']);
            if ($voiture) $depense->setVoiture($voiture);
        }

        if (isset($data['bureauId'])) {
            $bureau = $bureauRepo->find($data['bureauId']);
            if ($bureau) $depense->setBureau($bureau);
        }

        $em->persist($depense);
        $em->flush();

        return $this->json(['message' => 'Dépense créée', 'id' => $depense->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Depense $depense, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['date']))         $depense->setDate(new \DateTimeImmutable($data['date']));
        if (isset($data['typeDepense']))  $depense->setTypeDepense($data['typeDepense']);
        if (isset($data['description']))  $depense->setDescription($data['description']);
        if (isset($data['montant']))      $depense->setMontant($data['montant']);
        // if (isset($data['statut']))       $depense->setStatut( StatusEnum::from($data['statut']));
        if (isset($data['statut'])) {

            try {
                $depense->setStatut(
                    StatusEnum::from($data['statut'])
                );

                } catch (\ValueError $e) {

                    return $this->json([
                        'message' => 'Statut invalide'
                    ], 400);
                }
        }
        if (isset($data['datePaiement'])) $depense->setDatePaiement(new \DateTimeImmutable($data['datePaiement']));
        $depense->setEditAu(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Dépense mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Depense $depense, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($depense);
        $em->flush();

        return $this->json(['message' => 'Dépense supprimée'], 204);
    }
}