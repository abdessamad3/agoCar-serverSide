<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\ClientDocument;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Standing list of clients flagged as a possible duplicate of an existing one
 * (set by PublicBookingController when a new booking's CIN/passport matches an
 * existing client) -- durable so a flag stays visible even if nobody acts on it
 * the moment a booking comes in, unlike the per-reservation surfacing in the
 * email/dashboard/reservation page. List/view stay open to any staff (ROLE_USER
 * via the global access_control rule); only merge is restricted, since it's the
 * one action here that actually changes existing data.
 */
#[Route('/api/client-duplicates', name: 'app_api_client_duplicates_')]
class ClientDuplicateController extends AbstractController
{
    use PaginationTrait;

    private function serializeClientRef(Client $c): array
    {
        return [
            'id'        => $c->getId(),
            'nom'       => $c->getNom(),
            'prenom'    => $c->getPrenom(),
            'telephone' => $c->getTelephone(),
            'email'     => $c->getEmail(),
        ];
    }

    private function serialize(Client $c): array
    {
        $matched = $c->getPossibleDuplicateOf();
        return [
            'id'            => $c->getId(),
            'nom'           => $c->getNom(),
            'prenom'        => $c->getPrenom(),
            'telephone'     => $c->getTelephone(),
            'email'         => $c->getEmail(),
            'cin'           => $c->getCin(),
            'passeport'     => $c->getPasseport(),
            'creeAu'        => $c->getCreeAu()?->format('Y-m-d H:i:s'),
            'matchedClient' => $matched ? $this->serializeClientRef($matched) : null,
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $page = $this->getPageParam($request);
        $qb = $em->createQueryBuilder()
            ->select('c')->from(Client::class, 'c')
            ->where('c.possibleDuplicateOf IS NOT NULL')
            ->andWhere('c.duplicateResolvedAt IS NULL')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('c.creeAu', 'DESC');
        [$items, $total] = $this->paginateQb($qb, $page);
        return $this->json([
            'data' => array_map(fn(Client $c) => $this->serialize($c), $items),
            'meta' => $this->paginateMeta($total, $page),
        ]);
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $client = $em->getRepository(Client::class)->find($id);
        if (!$client || $client->getDeletedAt() !== null) {
            return $this->json(['error' => 'Client introuvable.'], 404);
        }
        $matched = $client->getPossibleDuplicateOf();
        if (!$matched || $client->getDuplicateResolvedAt() !== null) {
            return $this->json(['error' => 'Aucun doublon en attente pour ce client.'], 422);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $action = $data['action'] ?? null;
        if (!in_array($action, ['merge', 'dismiss'], true)) {
            return $this->json(['error' => 'Le champ "action" doit valoir "merge" ou "dismiss".'], 422);
        }

        if ($action === 'merge') {
            // The one mutating path here -- reassigning real data -- stays restricted
            // to admins/managers, same convention as src/Controller/Api/TarifSaisonnierController.php.
            if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_MANAGER')) {
                throw $this->createAccessDeniedException('Seuls les administrateurs et gestionnaires peuvent fusionner des clients.');
            }

            $em->createQueryBuilder()
                ->update(Reservation::class, 'r')
                ->set('r.client', ':matched')
                ->where('r.client = :dup')
                ->setParameter('matched', $matched)
                ->setParameter('dup', $client)
                ->getQuery()
                ->execute();

            $em->createQueryBuilder()
                ->update(ClientDocument::class, 'd')
                ->set('d.client', ':matched')
                ->where('d.client = :dup')
                ->setParameter('matched', $matched)
                ->setParameter('dup', $client)
                ->getQuery()
                ->execute();

            // Soft-delete the now-empty duplicate -- its id/record stays as an inert
            // trace rather than disappearing outright.
            $client->setDeletedAt(new \DateTimeImmutable());
        }

        $client->setDuplicateResolvedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['success' => true, 'action' => $action]);
    }
}
