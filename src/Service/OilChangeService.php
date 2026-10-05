<?php

namespace App\Service;

use App\Entity\Voiture;
use App\Repository\VidangeRepository;
use App\Repository\VoitureRepository;

class OilChangeService
{
    public const OVERDUE  = 'OVERDUE';
    public const DUE_SOON = 'DUE_SOON';
    public const OK       = 'OK';
    public const UNKNOWN  = 'UNKNOWN';

    private const DUE_SOON_THRESHOLD = 1000;

    public function __construct(
        private VidangeRepository $vidangeRepo,
        private VoitureRepository $voitureRepo,
    ) {}

    /**
     * Bulk version of getOilStatus() — 1 query for all voitures instead of 1 per voiture.
     *
     * @param Voiture[] $voitures
     * @return array<int, array> keyed by voiture ID
     */
    public function getOilStatusBulk(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }

        $vidanges = $this->vidangeRepo->findLatestForVoitures($voitures);
        $result   = [];

        foreach ($voitures as $voiture) {
            $vid     = $voiture->getId();
            $vidange = $vidanges[$vid] ?? null;

            if ($vidange === null) {
                $result[$vid] = [
                    'status'          => self::UNKNOWN,
                    'currentKm'       => $voiture->getKilometrageActuel(),
                    'nextOilChangeKm' => null,
                    'remainingKm'     => null,
                    'intervalleKm'    => null,
                    'lastDate'        => null,
                    'vidangeId'       => null,
                ];
                continue;
            }

            $currentKm   = $voiture->getKilometrageActuel() ?? 0;
            $nextKm      = $vidange->getKilometrageSuivant() ?? 0;
            $remainingKm = $nextKm - $currentKm;

            $result[$vid] = [
                'status'          => $this->resolveStatus($remainingKm),
                'currentKm'       => $currentKm,
                'nextOilChangeKm' => $nextKm,
                'remainingKm'     => $remainingKm,
                'intervalleKm'    => $vidange->getIntervalleKm() ?? 10000,
                'lastDate'        => $vidange->getDepense()?->getDateDebut()?->format('Y-m-d'),
                'vidangeId'       => $vidange->getId(),
            ];
        }
        return $result;
    }

    public function getOilStatus(Voiture $voiture): array
    {
        $vidange = $this->vidangeRepo->findLatestByVoiture($voiture);

        if ($vidange === null) {
            return [
                'status'          => self::UNKNOWN,
                'currentKm'       => $voiture->getKilometrageActuel(),
                'nextOilChangeKm' => null,
                'remainingKm'     => null,
                'intervalleKm'    => null,
                'lastDate'        => null,
                'vidangeId'       => null,
            ];
        }

        $currentKm   = $voiture->getKilometrageActuel() ?? 0;
        $nextKm      = $vidange->getKilometrageSuivant() ?? 0;
        $remainingKm = $nextKm - $currentKm;

        return [
            'status'          => $this->resolveStatus($remainingKm),
            'currentKm'       => $currentKm,
            'nextOilChangeKm' => $nextKm,
            'remainingKm'     => $remainingKm,
            'intervalleKm'    => $vidange->getIntervalleKm() ?? 10000,
            'lastDate'        => $vidange->getDepense()?->getDateDebut()?->format('Y-m-d'),
            'vidangeId'       => $vidange->getId(),
        ];
    }

    public function getDashboardSummary(int $bureauId = 0): array
    {
        $latestByVoiture = $this->vidangeRepo->findLatestPerVoiture($bureauId);

        $dueSoon  = [];
        $overdue  = [];
        $allItems = [];

        foreach ($latestByVoiture as $voitureId => $vidange) {
            $voiture     = $vidange->getDepense()?->getVoiture();
            $currentKm   = $voiture?->getKilometrageActuel() ?? 0;
            $nextKm      = $vidange->getKilometrageSuivant() ?? 0;
            $remainingKm = $nextKm - $currentKm;
            $status      = $this->resolveStatus($remainingKm);

            $item = [
                'voitureId'       => $voitureId,
                'voiture'         => trim(($voiture?->getMarque() ?? '') . ' ' . ($voiture?->getModele() ?? '')),
                'immatriculation' => $voiture?->getImmatriculation() ?? '',
                'currentKm'       => $currentKm,
                'nextOilChangeKm' => $nextKm,
                'intervalleKm'    => $vidange->getIntervalleKm() ?? 10000,
                'remainingKm'     => $remainingKm,
                'status'          => $status,
                'vidangeId'       => $vidange->getId(),
                'lastDate'        => $vidange->getDepense()?->getDateDebut()?->format('Y-m-d'),
            ];

            if ($status === self::OVERDUE)  $overdue[]  = $item;
            if ($status === self::DUE_SOON) $dueSoon[]  = $item;
            $allItems[] = $item;
        }

        $unknownCount = 0;
        foreach ($this->voitureRepo->findActive($bureauId) as $v) {
            if (!isset($latestByVoiture[$v->getId()])) {
                $unknownCount++;
            }
        }

        return [
            'overdue'      => $overdue,
            'dueSoon'      => $dueSoon,
            'overdueCount' => count($overdue),
            'dueSoonCount' => count($dueSoon),
            'unknownCount' => $unknownCount,
            'all'          => $allItems,
        ];
    }

    private function resolveStatus(int $remainingKm): string
    {
        if ($remainingKm <= 0)                       return self::OVERDUE;
        if ($remainingKm <= self::DUE_SOON_THRESHOLD) return self::DUE_SOON;
        return self::OK;
    }
}
