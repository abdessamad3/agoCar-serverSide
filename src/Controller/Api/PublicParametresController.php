<?php

namespace App\Controller\Api;

use App\Entity\ParametresSociete;
use App\Repository\ParametresSocieteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Read-only, unauthenticated settings for the public car rental website:
 * contact info, ticker promo, opening hours, social links, and the stats
 * shown on the About page. Deliberately leaves out rc/ice/ifFiscal/cnss
 * (fiscal registration numbers) and raisonSociale/adresse, which have no
 * reason to be public and aren't used anywhere on the customer site.
 */
#[Route('/api/site/parametres', name: 'app_api_public_parametres_')]
class PublicParametresController extends AbstractController
{
    #[Route('', name: 'get', methods: ['GET'])]
    public function get(ParametresSocieteRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ps = $repo->find(1);
        if (!$ps) {
            $ps = new ParametresSociete();
            $ps->setId(1);
            $em->persist($ps);
            $em->flush();
        }

        $telephones = $ps->getTelephones();

        return $this->json([
            'telephone'        => $telephones[0] ?? null,
            'email'            => $ps->getEmail(),
            'whatsapp'         => $ps->getWhatsapp(),
            'promoMessage'     => $ps->getPromoMessage(),
            'horaires'         => $ps->getHoraires(),
            'facebookUrl'      => $ps->getFacebookUrl(),
            'instagramUrl'     => $ps->getInstagramUrl(),
            'tiktokUrl'        => $ps->getTiktokUrl(),
            'nombreClients'    => $ps->getNombreClients(),
            'villesDesservies' => $ps->getVillesDesservies(),
        ]);
    }
}
