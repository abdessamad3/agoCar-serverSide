<?php

namespace App\Controller\Api;

use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Read-only, unauthenticated endpoints for the public car rental website.
 * Only customer-relevant fields are exposed — no prixAchat, kilometrage,
 * vin, compliance/document data, or anything internal to fleet management.
 */
#[Route('/api/site/voitures', name: 'app_api_public_voiture_')]
class PublicVoitureController extends AbstractController
{
    private const DEFAULT_LIMIT = 20;

    /**
     * Guards against DB rows containing mis-encoded bytes (e.g. Latin-1 text
     * saved into a UTF-8 column), which otherwise makes json_encode() fail
     * for the whole response with "Malformed UTF-8 characters".
     */
    private static function utf8(?string $value): ?string
    {
        if ($value === null || $value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Most likely cause: Windows-1252 bytes (e.g. accented French text
        // typed on Windows) were saved into a UTF-8 column. Re-decode from
        // that instead of destroying the accents with '?' substitution.
        $fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        return $fixed !== false ? $fixed : preg_replace('/[\x80-\xFF]/', '', $value);
    }

    private function serializeCard(\App\Entity\Voiture $v): array
    {
        return [
            'id'            => $v->getId(),
            'marque'        => self::utf8($v->getMarque()),
            'modele'        => self::utf8($v->getModele()),
            'version'       => self::utf8($v->getVersion()),
            'annee'         => $v->getAnnee(),
            'categorie'     => self::utf8($v->getCategorie()),
            'transmission'  => self::utf8($v->getTransmission()),
            'typeCarburant' => self::utf8($v->getTypeCarburant()),
            'places'        => $v->getPlaces(),
            'portes'        => $v->getPortes(),
            'puissanceCv'   => $v->getPuissanceCv(),
            'climatisation' => $v->isClimatisation(),
            'couleur'       => self::utf8($v->getCouleur()),
            'prixJour'      => $v->getPrixJour(),
            'prixSemaine'   => $v->getPrixSemaine(),
            'prixMois'      => $v->getPrixMois(),
            'image'         => self::utf8($v->getImagePath()),
        ];
    }

    private function serializeDetail(\App\Entity\Voiture $v): array
    {
        return $this->serializeCard($v) + [
            'images' => array_values(array_map(
                fn($img) => ['id' => $img->getId(), 'path' => self::utf8($img->getImagePath()), 'position' => $img->getPosition()],
                $v->getImages()->toArray()
            )),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, VoitureRepository $repo): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', self::DEFAULT_LIMIT)));

        $filters = [
            'categorie'    => $request->query->get('categorie', ''),
            'transmission' => $request->query->get('transmission', ''),
            'places'       => $request->query->get('places', ''),
            'prixMax'      => $request->query->get('prixMax', ''),
        ];

        $dateDebutStr = $request->query->get('dateDebut', '');
        $dateFinStr   = $request->query->get('dateFin', '');
        if ($dateDebutStr && $dateFinStr) {
            try {
                $dateDebut = new \DateTimeImmutable($dateDebutStr);
                $dateFin   = new \DateTimeImmutable($dateFinStr);
                if ($dateDebut <= $dateFin) {
                    $filters['excludeIds'] = $repo->findBookedVoitureIdsForPeriod($dateDebut, $dateFin);
                }
            } catch (\Exception) {
                // ignore malformed dates — fall back to unfiltered-by-availability results
            }
        }

        $items = $repo->findPublicAvailable($filters, $page, $limit);
        $total = $repo->countPublicAvailable($filters);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->json([
            'data' => array_map(fn($v) => $this->serializeCard($v), $items),
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'totalPages'  => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1,
            ],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $voiture = $em->getRepository(\App\Entity\Voiture::class)->find($id);

        if (!$voiture || $voiture->getDeletedAt() !== null || $voiture->getVoitureStatus() !== 'disponible') {
            return $this->json(['error' => 'Véhicule introuvable'], 404);
        }

        return $this->json($this->serializeDetail($voiture));
    }

    #[Route('/{id}/blocked-periods', name: 'blocked_periods', methods: ['GET'])]
    public function blockedPeriods(int $id, EntityManagerInterface $em): JsonResponse
    {
        $voiture = $em->getRepository(\App\Entity\Voiture::class)->find($id);
        if (!$voiture || $voiture->getDeletedAt() !== null) {
            return $this->json([]);
        }

        $today = new \DateTimeImmutable('today');
        $rows  = $em->createQueryBuilder()
            ->select('r.dateDebut, r.dateFin')
            ->from(\App\Entity\Reservation::class, 'r')
            ->where('r.voiture = :voiture')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.reservationStatus IN (:active)')
            ->andWhere('r.dateFin >= :today')
            ->setParameter('voiture', $voiture)
            ->setParameter('active', ['confirmed', 'confirmee', 'en_cours', 'louee'])
            ->setParameter('today', $today)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn($r) => [
            'start' => $r['dateDebut']->format('Y-m-d'),
            'end'   => $r['dateFin']->format('Y-m-d'),
        ], $rows));
    }
}
