<?php

namespace App\Service;

use App\Entity\Credit;
use App\Entity\Notification;
use App\Entity\Utilisateur;
use App\Entity\Voiture;
use App\Repository\CreditRepository;
use App\Repository\NotificationRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    // ── Types ─────────────────────────────────────────────────────────────────
    public const TYPE_COMPLIANCE_EXPIRED   = 'compliance_expired';
    public const TYPE_COMPLIANCE_WARNING   = 'compliance_warning';
    public const TYPE_OIL_CHANGE_DUE      = 'oil_change_due';
    public const TYPE_OIL_CHANGE_OVERDUE  = 'oil_change_overdue';
    public const TYPE_CREDIT_DUE          = 'credit_due';
    public const TYPE_CREDIT_OVERDUE      = 'credit_overdue';
    public const TYPE_RESERVATION_CREATED  = 'reservation_created';
    public const TYPE_RESERVATION_CONFLICT = 'reservation_conflict';
    public const TYPE_VEHICLE_SOLD         = 'vehicle_sold';

    // Legacy aliases (kept so existing DB rows still display correctly)
    public const TYPE_COMPLIANCE_INSURANCE = 'compliance_insurance';
    public const TYPE_COMPLIANCE_VIGNETTE  = 'compliance_vignette';
    public const TYPE_COMPLIANCE_VISITE    = 'compliance_visite';
    public const TYPE_OIL_CHANGE           = 'oil_change';
    public const TYPE_CREDIT_INSTALLMENT   = 'credit_installment';

    // ── Priorities ────────────────────────────────────────────────────────────
    public const PRIORITY_LOW      = 'LOW';
    public const PRIORITY_MEDIUM   = 'MEDIUM';
    public const PRIORITY_HIGH     = 'HIGH';
    public const PRIORITY_CRITICAL = 'CRITICAL';

    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $notifRepo,
        private UtilisateurRepository  $utilisateurRepo,
        private VoitureRepository      $voitureRepo,
        private CreditRepository       $creditRepo,
        private ComplianceService      $complianceService,
        private OilChangeService       $oilChangeService,
    ) {}

    // ── Public: manual one-off notification ───────────────────────────────────

    /**
     * Create (or upsert) a notification for a single user and immediately flush.
     */
    public function create(
        Utilisateur $user,
        string $type,
        string $sourceType,
        int $sourceId,
        string $title,
        string $message,
        string $priority = self::PRIORITY_MEDIUM,
        ?string $deepLink = null,
    ): void {
        $this->upsert($user, $type, $sourceType, $sourceId, $title, $message, $priority, $deepLink);
        $this->em->flush();
    }

    /**
     * Broadcast a notification to every active user and immediately flush.
     */
    public function createForAllUsers(
        string $type,
        string $sourceType,
        int $sourceId,
        string $title,
        string $message,
        string $priority = self::PRIORITY_MEDIUM,
        ?string $deepLink = null,
    ): void {
        $users = $this->utilisateurRepo->findActive();
        foreach ($users as $user) {
            $this->upsert($user, $type, $sourceType, $sourceId, $title, $message, $priority, $deepLink);
        }
        $this->em->flush();
    }

    // ── Public: sync (cron) ───────────────────────────────────────────────────

    public function syncAll(): void
    {
        $users = $this->utilisateurRepo->findActive();
        if (!$users) return;

        $this->syncCompliance($users);
        $this->syncOilChanges($users);
        $this->syncCreditInstallments($users);

        $this->em->flush();
    }

    // ── Compliance ────────────────────────────────────────────────────────────

    private function syncCompliance(array $users): void
    {
        foreach ($this->voitureRepo->findActive() as $voiture) {
            $status = $this->complianceService->getComplianceStatus($voiture);
            $label  = $voiture->getMarque() . ' ' . $voiture->getModele()
                    . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '');

            $this->syncComplianceDoc(
                $users, $voiture, $status['assurance'],
                'Assurance', $label, '/assurance'
            );
            $this->syncComplianceDoc(
                $users, $voiture, $status['vignette'],
                'Vignette', $label, '/vignette'
            );
            $this->syncComplianceDoc(
                $users, $voiture, $status['visite'],
                'Visite technique', $label, '/suivi-technique'
            );
        }
    }

    /**
     * Reminder tiers per the business requirement (90/60/30/15/7/1 days before
     * expiry). These are intentionally finer-grained than ComplianceService's
     * own VALID/WARNING/CRITICAL/EXPIRED status, which only starts flagging at
     * 30 days — the 90 and 60-day tiers below fire while status is still VALID,
     * as advance notice, before the document is otherwise considered a problem.
     *
     * @return array{0: string, 1: string} [priority, tier label]
     */
    private function complianceTier(?int $days): array
    {
        if ($days === null) return [self::PRIORITY_MEDIUM, 'Rappel'];
        if ($days <= 1)      return [self::PRIORITY_CRITICAL, 'Dernier rappel'];
        if ($days <= 7)      return [self::PRIORITY_CRITICAL, 'Critique'];
        if ($days <= 15)     return [self::PRIORITY_HIGH, 'Urgent'];
        if ($days <= 30)     return [self::PRIORITY_HIGH, 'Attention'];
        if ($days <= 60)     return [self::PRIORITY_MEDIUM, 'Rappel'];
        return [self::PRIORITY_LOW, 'Préavis']; // <= 90
    }

    private function syncComplianceDoc(
        array $users,
        Voiture $voiture,
        array $info,
        string $docLabel,
        string $carLabel,
        string $listRoute,
    ): void {
        $status    = $info['status'];
        $days      = $info['daysRemaining'];
        $sourceKey = strtolower(str_replace(' ', '_', $docLabel));

        if ($status === ComplianceService::NOT_REQUIRED || $status === ComplianceService::UPCOMING) {
            $this->resolveForSource('voiture_' . $sourceKey, $voiture->getId());
            return;
        }

        if ($status === ComplianceService::VALID && ($days === null || $days > 90)) {
            $this->resolveForSource('voiture_' . $sourceKey, $voiture->getId());
            return;
        }

        if ($status === ComplianceService::EXPIRED) {
            $type     = self::TYPE_COMPLIANCE_EXPIRED;
            $priority = self::PRIORITY_CRITICAL;
            $title    = "$docLabel expiré — $carLabel";
            $message  = $days !== null
                ? "$docLabel expiré depuis " . abs((int) $days) . " jour(s)."
                : "$docLabel a expiré.";
        } else {
            // VALID-but-within-90-days, WARNING, or CRITICAL — graded by the
            // 90/60/30/15/7/1-day tiers rather than the coarser status alone.
            $type = self::TYPE_COMPLIANCE_WARNING;
            [$priority, $tierLabel] = $this->complianceTier($days);
            $title   = "$docLabel expire dans $days jour(s) — $carLabel";
            $message = "$tierLabel : $docLabel expire dans $days jour(s).";
        }

        $deepLink = '/voiture/' . $voiture->getId();

        foreach ($users as $user) {
            $this->upsert(
                $user, $type,
                'voiture_' . $sourceKey, $voiture->getId(),
                $title, $message, $priority, $deepLink
            );
        }
    }

    // ── Oil change ────────────────────────────────────────────────────────────

    private function syncOilChanges(array $users): void
    {
        foreach ($this->voitureRepo->findActive() as $voiture) {
            $oil   = $this->oilChangeService->getOilStatus($voiture);
            $label = $voiture->getMarque() . ' ' . $voiture->getModele()
                   . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '');

            if ($oil['status'] === OilChangeService::OK || $oil['status'] === OilChangeService::UNKNOWN) {
                $this->resolveForSource('voiture_oil', $voiture->getId());
                continue;
            }

            $deepLink = '/voiture/' . $voiture->getId();

            if ($oil['status'] === OilChangeService::OVERDUE) {
                $type     = self::TYPE_OIL_CHANGE_OVERDUE;
                $priority = self::PRIORITY_HIGH;
                $title    = "Vidange en retard — $label";
                $message  = "Vidange dépassée de " . abs((int) $oil['remainingKm']) . " km.";
            } else {
                $type     = self::TYPE_OIL_CHANGE_DUE;
                $priority = self::PRIORITY_MEDIUM;
                $title    = "Vidange à prévoir — $label";
                $message  = "Vidange dans {$oil['remainingKm']} km (prochain à {$oil['nextOilChangeKm']} km).";
            }

            foreach ($users as $user) {
                $this->upsert($user, $type, 'voiture_oil', $voiture->getId(), $title, $message, $priority, $deepLink);
            }
        }
    }

    // ── Credit installments ───────────────────────────────────────────────────

    private function syncCreditInstallments(array $users): void
    {
        foreach ($this->creditRepo->findActive() as $credit) {
            $dueStatus = $this->getCreditDueStatus($credit);
            $voiture   = $credit->getVoiture();
            $label     = $voiture
                ? $voiture->getMarque() . ' ' . $voiture->getModele()
                : 'Credit #' . $credit->getId();

            if ($dueStatus === null) {
                $this->resolveForSource('credit', $credit->getId());
                continue;
            }

            $montant  = number_format((float) $credit->getMensualite(), 2);
            $deepLink = '/mensualite';

            if ($dueStatus === 'OVERDUE') {
                $type     = self::TYPE_CREDIT_OVERDUE;
                $priority = self::PRIORITY_CRITICAL;
                $title    = "Mensualité en retard — $label";
                $message  = "La mensualité de $montant MAD est en retard.";
            } else {
                $type     = self::TYPE_CREDIT_DUE;
                $priority = self::PRIORITY_HIGH;
                $title    = "Mensualité à payer — $label";
                $message  = "La mensualité de $montant MAD est due dans 7 jours.";
            }

            foreach ($users as $user) {
                $this->upsert($user, $type, 'credit', $credit->getId(), $title, $message, $priority, $deepLink);
            }
        }
    }

    private function getCreditDueStatus(Credit $credit): ?string
    {
        $today         = new \DateTimeImmutable('today');
        $paymentsCount = $credit->getPaiements()->count();
        $dureeMois     = $credit->getDureeMois() ?? 0;

        if ($paymentsCount >= $dureeMois) return null;

        $nextDue   = $credit->getDateDebut()->modify("+{$paymentsCount} months");
        $diff      = (int) $today->diff($nextDue)->days;
        $remaining = $nextDue >= $today ? $diff : -$diff;

        if ($remaining <= 0)  return 'OVERDUE';
        if ($remaining <= 7)  return 'DUE_SOON';
        return null;
    }

    // ── Upsert / resolve ──────────────────────────────────────────────────────

    private function upsert(
        Utilisateur $user,
        string $type,
        string $sourceType,
        int $sourceId,
        string $title,
        string $message,
        string $priority = self::PRIORITY_MEDIUM,
        ?string $deepLink = null,
    ): void {
        $existing = $this->notifRepo->findOneByUserAndSource($user, $sourceType, $sourceId);

        if ($existing) {
            $existing->setTitle($title);
            $existing->setMessage($message);
            $existing->setType($type);
            $existing->setPriority($priority);
            if ($deepLink !== null) {
                $existing->setDeepLink($deepLink);
            }
        } else {
            $notif = (new Notification())
                ->setUser($user)
                ->setType($type)
                ->setSourceType($sourceType)
                ->setSourceId($sourceId)
                ->setTitle($title)
                ->setMessage($message)
                ->setPriority($priority)
                ->setDeepLink($deepLink);
            $this->em->persist($notif);
        }
    }

    private function resolveForSource(string $sourceType, int $sourceId): void
    {
        foreach ($this->notifRepo->findBySource($sourceType, $sourceId) as $notif) {
            $this->em->remove($notif);
        }
    }
}
