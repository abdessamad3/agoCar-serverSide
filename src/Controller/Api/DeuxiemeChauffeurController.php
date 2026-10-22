<?php

namespace App\Controller\Api;

use App\Entity\DeuxiemeChauffeur;
use App\Service\ReservationPricingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Standalone edit for a second driver already attached to a reservation -- the
 * dossier page's inline "edit driver" modal calls this directly (PUT by id) rather
 * than going through ReservationController's update(), which previously had no
 * matching route at all and silently failed on every save.
 */
#[Route('/api/deuxieme-chauffeur', name: 'app_api_deuxieme_chauffeur_')]
#[IsGranted('ROLE_USER')]
class DeuxiemeChauffeurController extends AbstractController
{
    public function __construct(private readonly ReservationPricingService $pricing) {}

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(DeuxiemeChauffeur $dc, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $dc->applyFromArray($data);
        $em->flush();

        // A changed prix changes the reservation's total -- same guard LocationController's
        // cloture() and ReservationController's update() already use, so editing a driver's
        // fee here can't silently overwrite a closed reservation's finalized total.
        $reservation = $dc->getReservation();
        if ($reservation && !$reservation->isClosed()) {
            $voiture = $reservation->getVoiture();
            $debut   = $reservation->getDateDebut();
            $fin     = $reservation->getDateFin();
            if ($voiture && $debut && $fin) {
                $pricing = $this->pricing->computeTotal(
                    $voiture, $debut, $fin,
                    $reservation->getAccessoires(), $reservation->getRemiseMontant(),
                    $dc->getPrix(),
                );
                $reservation->setPrixParJour($pricing['prixParJour']);
                $reservation->setTotal($pricing['total']);
                $em->flush();
            }
        }

        return $this->json([
            'message' => 'Conducteur mis à jour',
            'total'   => $reservation?->getTotal(),
        ]);
    }
}
