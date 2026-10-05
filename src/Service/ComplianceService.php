<?php

namespace App\Service;

use App\Entity\Voiture;
use App\Repository\VignetteRepository;
use App\Repository\AssuranceRepository;
use App\Repository\SuiviTechniqueRepository;

class ComplianceService
{
    public const VALID        = 'VALID';
    public const WARNING      = 'WARNING';
    public const CRITICAL     = 'CRITICAL';
    public const EXPIRED      = 'EXPIRED';
    public const UPCOMING     = 'UPCOMING';
    public const UNKNOWN      = 'UNKNOWN';
    public const NOT_REQUIRED = 'NOT_REQUIRED';

    /**
     * UPCOMING ranks alongside VALID (not worse) so it never makes a vehicle's
     * overall status look non-compliant on its own — it's purely informational
     * ("this document's term hasn't started yet"). This matters most right after
     * an early renewal: the just-archived previous document may still be the one
     * actually covering today, but compliance only looks at the latest non-archived
     * record, so the new (UPCOMING) one must not be treated as a problem.
     */
    private const STATUS_RANK = [
        self::EXPIRED       => 4,
        self::CRITICAL      => 3,
        self::WARNING       => 2,
        self::VALID         => 1,
        self::UPCOMING      => 1,
        self::UNKNOWN       => 0,
        self::NOT_REQUIRED  => -1,
    ];

    public function __construct(
        private VignetteRepository        $vignetteRepo,
        private AssuranceRepository       $assuranceRepo,
        private SuiviTechniqueRepository  $suiviRepo,
    ) {}

    /**
     * Bulk version of getComplianceStatus() — fetches all documents in 3 queries total
     * instead of 3 queries per voiture, eliminating the N+1 problem on list pages.
     *
     * @param Voiture[] $voitures
     * @return array<int, array> keyed by voiture ID
     */
    public function getComplianceStatusBulk(array $voitures): array
    {
        if (empty($voitures)) {
            return [];
        }

        $vignettes  = $this->vignetteRepo->findLatestForVoitures($voitures);
        $assurances = $this->assuranceRepo->findLatestForVoitures($voitures);
        $suivis     = $this->suiviRepo->findLatestForVoitures($voitures);

        $result = [];
        foreach ($voitures as $voiture) {
            $vid       = $voiture->getId();
            $vigInfo   = $this->resolveStatus(
                $vignettes[$vid]??null  ? $vignettes[$vid]->getDepense()?->getDateFin()   : null,
                true,
                $vignettes[$vid]??null  ? $vignettes[$vid]->getDepense()?->getDateDebut()  : null
            );
            $assInfo   = $this->resolveStatus(
                $assurances[$vid]??null ? $assurances[$vid]->getDepense()?->getDateFin() : null,
                true,
                $assurances[$vid]??null ? $assurances[$vid]->getDepense()?->getDateDebut() : null
            );
            $visInfo   = $this->resolveStatus(
                $suivis[$vid]??null ? $suivis[$vid]->getDepense()?->getDateFin() : null,
                $this->getVehicleAgeYears($voiture) >= 3,
                $suivis[$vid]??null ? $suivis[$vid]->getDepense()?->getDateDebut() : null
            );
            $result[$vid] = [
                'overall'   => $this->worstStatus([$vigInfo['status'], $assInfo['status'], $visInfo['status']]),
                'vignette'  => $vigInfo,
                'assurance' => $assInfo,
                'visite'    => $visInfo,
            ];
        }
        return $result;
    }

    public function getComplianceStatus(Voiture $voiture): array
    {
        $vignette  = $this->vignetteRepo->findLatestByVoiture($voiture);
        $assurance = $this->assuranceRepo->findLatestByVoiture($voiture);
        $suivi     = $this->suiviRepo->findLatestByVoiture($voiture);

        $vignetteInfo  = $this->resolveStatus($vignette?->getDepense()?->getDateFin(), true, $vignette?->getDepense()?->getDateDebut());
        $assuranceInfo = $this->resolveStatus($assurance?->getDepense()?->getDateFin(), true, $assurance?->getDepense()?->getDateDebut());
        $visiteInfo    = $this->resolveStatus($suivi?->getDepense()?->getDateFin(), $this->getVehicleAgeYears($voiture) >= 3, $suivi?->getDepense()?->getDateDebut());

        $overall = $this->worstStatus([
            $vignetteInfo['status'],
            $assuranceInfo['status'],
            $visiteInfo['status'],
        ]);

        return [
            'overall'   => $overall,
            'vignette'  => $vignetteInfo,
            'assurance' => $assuranceInfo,
            'visite'    => $visiteInfo,
        ];
    }

    private function resolveStatus(?\DateTimeInterface $expiration, bool $required = true, ?\DateTimeInterface $startDate = null): array
    {
        if (!$required) {
            return ['status' => self::NOT_REQUIRED, 'daysRemaining' => null, 'expiresAt' => null];
        }

        if ($expiration === null) {
            return ['status' => self::UNKNOWN, 'daysRemaining' => null, 'expiresAt' => null];
        }

        // dateFin/dateDebut are plain calendar dates with no real time-of-day
        // meaning. Rebuild everything from Y-m-d in the business's timezone so
        // the diff() below compares calendar days, not absolute instants —
        // mixing a Casablanca "today" with an expiration carrying the server's
        // ambient timezone (whatever that happens to be) would skew the diff
        // by the timezone offset instead of fixing it.
        $tz         = new \DateTimeZone('Africa/Casablanca');
        $today      = new \DateTimeImmutable('today', $tz);
        $expiration = new \DateTimeImmutable($expiration->format('Y-m-d'), $tz);
        $startDate  = $startDate !== null ? new \DateTimeImmutable($startDate->format('Y-m-d'), $tz) : null;

        if ($startDate !== null && $startDate > $today) {
            $daysUntilStart = (int) $today->diff($startDate)->days;
            return [
                'status'        => self::UPCOMING,
                'daysRemaining' => $daysUntilStart,
                'expiresAt'     => $expiration->format('Y-m-d'),
                'startsAt'      => $startDate->format('Y-m-d'),
            ];
        }

        $diff          = (int) $today->diff($expiration)->days;
        $daysRemaining = $expiration >= $today ? $diff : -$diff;

        if ($daysRemaining < 0)       $status = self::EXPIRED;
        elseif ($daysRemaining <= 7)  $status = self::CRITICAL;
        elseif ($daysRemaining <= 30) $status = self::WARNING;
        else                          $status = self::VALID;

        return [
            'status'        => $status,
            'daysRemaining' => $daysRemaining,
            'expiresAt'     => $expiration->format('Y-m-d'),
        ];
    }

    private function getVehicleAgeYears(Voiture $voiture): int
    {
        $annee = $voiture->getAnnee();
        if ($annee === null) return 99;
        return (int) (new \DateTimeImmutable())->format('Y') - $annee;
    }

    private function worstStatus(array $statuses): string
    {
        $worst = self::VALID;
        foreach ($statuses as $s) {
            if ($s === self::NOT_REQUIRED) continue;
            if ((self::STATUS_RANK[$s] ?? 0) > (self::STATUS_RANK[$worst] ?? 0)) {
                $worst = $s;
            }
        }
        return $worst;
    }

    public function isBlocking(Voiture $voiture): bool
    {
        return $this->getComplianceStatus($voiture)['overall'] === self::EXPIRED;
    }

    public function isVisiteRequired(Voiture $voiture): bool
    {
        return $this->getVehicleAgeYears($voiture) >= 3;
    }
}
