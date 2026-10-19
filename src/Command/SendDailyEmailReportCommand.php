<?php

namespace App\Command;

use App\Entity\EmailLog;
use App\Entity\Notification;
use App\Entity\VehicleCredit;
use App\Entity\VehicleCreditInstallment;
use App\Repository\CreditRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\VoitureRepository;
use App\Service\ComplianceService;
use App\Service\OilChangeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Not currently triggered automatically anywhere — requires a host-level
 * crontab entry (no in-app scheduler/worker is configured in this project):
 *
 *   0 10 * * * cd /path/to/autoloc && php bin/console app:send-daily-report >> var/log/daily-report.log 2>&1
 *
 * Test it manually first with --force (sends even with zero alerts, to verify SMTP):
 *   php bin/console app:send-daily-report --force
 */
#[AsCommand(
    name: 'app:send-daily-report',
    description: 'Send the daily compliance, oil change, and credit alert email at 10 AM — requires a crontab entry, see class docblock',
)]
class SendDailyEmailReportCommand extends Command
{
    public function __construct(
        private VoitureRepository      $voitureRepo,
        private CreditRepository       $creditRepo,
        private UtilisateurRepository  $utilisateurRepo,
        private ComplianceService      $complianceService,
        private OilChangeService       $oilChangeService,
        private MailerInterface        $mailer,
        private EntityManagerInterface $em,
        private string                 $senderEmail,
        private string                 $recipientEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Send the email even if there are no alerts (useful for testing SMTP)');
        $this->addOption('triggered-by', null, InputOption::VALUE_OPTIONAL, 'Who triggered this (scheduler|manual)', 'scheduler');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $force       = $input->getOption('force');
        $triggeredBy = $input->getOption('triggered-by') ?? 'scheduler';

        $this->createVehicleCreditNotifications();
        $this->createComplianceNotifications();
        $this->createOilChangeNotifications();

        $compliance    = $this->collectComplianceAlerts();
        $oil           = $this->collectOilAlerts();
        $credits       = $this->collectCreditAlerts();
        $vcInstallments = $this->collectVehicleCreditInstallmentAlerts();

        $totalAlerts = count($compliance) + count($oil) + count($credits) + count($vcInstallments);

        if ($totalAlerts === 0 && !$force) {
            $io->success('No alerts today — no email sent. Use --force to send a test email.');
            return Command::SUCCESS;
        }

        // Managers go in To (visible, primary), staff in Cc (visible, but not
        // primary), admins always in Bcc (they must always get the report,
        // but must never appear as a visible recipient to managers/staff).
        // Recipients are additionally grouped by their own preferred language
        // (synced from the UI language toggle) -- one email per language
        // present, each recipient only seeing the group they're actually in.
        $byLang = [];
        foreach ($this->utilisateurRepo->findBy(['actif' => true]) as $u) {
            if (!$u->getEmail()) continue;
            $lang = in_array($u->getLangue(), ['en', 'fr', 'ar'], true) ? $u->getLangue() : 'fr';
            $byLang[$lang] ??= ['managers' => [], 'staff' => [], 'admins' => []];
            $roles = $u->getRoles();
            if (in_array('ROLE_ADMIN', $roles, true))        $byLang[$lang]['admins'][]   = $u->getEmail();
            elseif (in_array('ROLE_MANAGER', $roles, true))  $byLang[$lang]['managers'][] = $u->getEmail();
            elseif (in_array('ROLE_STAFF', $roles, true))    $byLang[$lang]['staff'][]    = $u->getEmail();
        }
        if (!$byLang) {
            // Nobody active with an email at all -- fall back to the single
            // configured recipient so the report is still deliverable.
            $byLang['fr'] = ['managers' => [$this->recipientEmail], 'staff' => [], 'admins' => []];
        }

        $sentCount = 0;
        $failed    = false;
        foreach ($byLang as $lang => $buckets) {
            $managers = array_values(array_unique($buckets['managers']));
            $staff    = array_values(array_unique(array_diff($buckets['staff'], $managers)));
            $bcc      = array_values(array_unique(array_diff($buckets['admins'], $managers, $staff)));

            // Edge case: no managers in this language group -- fall back to
            // the configured recipient so the group's alerts still go out.
            $to = $managers ?: [$this->recipientEmail];

            $t       = $this->labels($lang);
            $subject = sprintf($t['emailSubject'], $totalAlerts, date('d/m/Y'));
            $html    = $this->buildHtml($compliance, $oil, $credits, $vcInstallments, $totalAlerts, $lang);

            $log = new EmailLog();
            $log->setRecipientEmail(implode(', ', $to));
            $log->setSubject($subject);
            $log->setTotalAlerts($totalAlerts);
            $log->setComplianceCount(count($compliance));
            $log->setOilCount(count($oil));
            $log->setCreditCount(count($credits) + count($vcInstallments));
            $log->setTriggeredBy($triggeredBy);

            try {
                $email = (new Email())
                    ->from($this->senderEmail)
                    ->to(...$to)
                    ->subject($subject)
                    ->html($html);

                if ($staff) $email->cc(...$staff);
                if ($bcc)   $email->bcc(...$bcc);

                $this->mailer->send($email);

                $log->setStatus('sent');
                $sentCount++;
                $io->success(sprintf(
                    '[%s] Daily report sent to %s (cc: %s, bcc: %s) — %d alert(s).',
                    $lang, implode(', ', $to), implode(', ', $staff), implode(', ', $bcc), $totalAlerts
                ));
            } catch (\Throwable $e) {
                $failed = true;
                $log->setStatus('failed');
                $log->setErrorMessage($e->getMessage());
                $io->error("[$lang] Failed to send email: " . $e->getMessage());
            } finally {
                $this->em->persist($log);
            }
        }
        $this->em->flush();

        return ($sentCount > 0 || !$failed) ? Command::SUCCESS : Command::FAILURE;
    }

    // ── VehicleCredit in-app notifications ────────────────────────────────────

    private function createVehicleCreditNotifications(): void
    {
        $today = new \DateTimeImmutable('today');
        $users = $this->utilisateurRepo->findAll();
        if (!$users) return;

        $activeCredits = $this->em->createQueryBuilder()
            ->select('vc')
            ->from(VehicleCredit::class, 'vc')
            ->where("vc.status = 'active'")
            ->getQuery()
            ->getResult();

        foreach ($activeCredits as $vc) {
            foreach ($vc->getInstallments() as $inst) {
                if ($inst->getStatus() === 'paid') continue;

                $due      = \DateTimeImmutable::createFromInterface($inst->getDueDate());
                $daysLeft = (int) $today->diff($due)->format('%r%a');

                if ($daysLeft < 0 || $daysLeft > 5) continue;

                $voiture = $vc->getVoiture();
                $carName = $voiture ? trim(($voiture->getMarque() ?? '') . ' ' . ($voiture->getModele() ?? '')) : 'Véhicule';
                $title   = $daysLeft === 0
                    ? "Échéance crédit aujourd'hui — {$carName}"
                    : "Crédit {$carName} — {$daysLeft}j avant échéance";
                $message = sprintf(
                    'Mensualité n°%d de %.2f MAD due le %s.',
                    $inst->getInstallmentNumber(),
                    (float) $inst->getAmountDue(),
                    $due->format('d/m/Y')
                );
                $deepLink = $voiture ? '/voiture/' . $voiture->getId() . '?tab=credit' : null;

                foreach ($users as $user) {
                    $existing = $this->em->createQueryBuilder()
                        ->select('COUNT(n.id)')
                        ->from(Notification::class, 'n')
                        ->where('n.user = :u')
                        ->andWhere('n.sourceType = :st')
                        ->andWhere('n.sourceId = :sid')
                        ->setParameter('u', $user)
                        ->setParameter('st', 'vehicle_credit_installment')
                        ->setParameter('sid', $inst->getId())
                        ->getQuery()
                        ->getSingleScalarResult();

                    if ((int) $existing > 0) continue;

                    $notif = new Notification();
                    $notif->setUser($user);
                    $notif->setTitle($title);
                    $notif->setMessage($message);
                    $notif->setType('credit_payment');
                    $notif->setSourceType('vehicle_credit_installment');
                    $notif->setSourceId($inst->getId());
                    $notif->setPriority($daysLeft <= 1 ? 'HIGH' : 'MEDIUM');
                    $notif->setDeepLink($deepLink);
                    $this->em->persist($notif);
                }
            }
        }

        $this->em->flush();
    }

    // ── Compliance in-app notifications ──────────────────────────────────────

    private function createComplianceNotifications(): void
    {
        $users = $this->utilisateurRepo->findAll();
        if (!$users) return;

        $docLabels = [
            'assurance' => 'Assurance',
            'vignette'  => 'Vignette',
            'visite'    => 'Visite technique',
        ];

        foreach ($this->voitureRepo->findActive() as $voiture) {
            $status  = $this->complianceService->getComplianceStatus($voiture);
            $carName = trim(($voiture->getMarque() ?? '') . ' ' . ($voiture->getModele() ?? ''));
            $deepLink = '/voiture/' . $voiture->getId() . '?tab=conformite';

            foreach ($docLabels as $key => $docLabel) {
                $info = $status[$key];
                if (!in_array($info['status'], [ComplianceService::WARNING, ComplianceService::CRITICAL, ComplianceService::EXPIRED], true)) {
                    continue;
                }

                $sourceType = 'compliance_' . $key;
                $sourceId   = $voiture->getId();
                $priority   = match($info['status']) {
                    ComplianceService::EXPIRED  => 'CRITICAL',
                    ComplianceService::CRITICAL => 'HIGH',
                    default                     => 'MEDIUM',
                };
                $days  = $info['daysRemaining'];
                $title = match($info['status']) {
                    ComplianceService::EXPIRED  => "{$docLabel} expiré — {$carName}",
                    ComplianceService::CRITICAL => "{$docLabel} critique — {$carName}",
                    default                     => "{$docLabel} bientôt — {$carName}",
                };
                $message = $days !== null && $days >= 0
                    ? "Expire dans {$days} jour(s) : " . ($info['expiresAt'] ?? '')
                    : "Expiré depuis " . abs((int)$days) . " jour(s).";

                foreach ($users as $user) {
                    $existing = $this->em->createQueryBuilder()
                        ->select('COUNT(n.id)')
                        ->from(Notification::class, 'n')
                        ->where('n.user = :u')
                        ->andWhere('n.sourceType = :st')
                        ->andWhere('n.sourceId = :sid')
                        ->setParameter('u', $user)
                        ->setParameter('st', $sourceType)
                        ->setParameter('sid', $sourceId)
                        ->getQuery()->getSingleScalarResult();

                    if ((int) $existing > 0) continue;

                    $notif = new Notification();
                    $notif->setUser($user);
                    $notif->setTitle($title);
                    $notif->setMessage($message);
                    $notif->setType('compliance_' . $key);
                    $notif->setSourceType($sourceType);
                    $notif->setSourceId($sourceId);
                    $notif->setPriority($priority);
                    $notif->setDeepLink($deepLink);
                    $this->em->persist($notif);
                }
            }
        }

        $this->em->flush();
    }

    private function createOilChangeNotifications(): void
    {
        $users = $this->utilisateurRepo->findAll();
        if (!$users) return;

        foreach ($this->voitureRepo->findActive() as $voiture) {
            $oil = $this->oilChangeService->getOilStatus($voiture);
            if (!in_array($oil['status'], [OilChangeService::DUE_SOON, OilChangeService::OVERDUE], true)) continue;

            $carName    = trim(($voiture->getMarque() ?? '') . ' ' . ($voiture->getModele() ?? ''));
            $sourceType = 'oil_change';
            $sourceId   = $voiture->getId();
            $isOverdue  = $oil['status'] === OilChangeService::OVERDUE;
            $title      = $isOverdue ? "Vidange en retard — {$carName}" : "Vidange bientôt — {$carName}";
            $km         = (int) ($oil['remainingKm'] ?? 0);
            $message    = $isOverdue
                ? "Vidange dépassée de " . abs($km) . " km."
                : "Vidange dans {$km} km.";

            foreach ($users as $user) {
                $existing = $this->em->createQueryBuilder()
                    ->select('COUNT(n.id)')
                    ->from(Notification::class, 'n')
                    ->where('n.user = :u')
                    ->andWhere('n.sourceType = :st')
                    ->andWhere('n.sourceId = :sid')
                    ->setParameter('u', $user)
                    ->setParameter('st', $sourceType)
                    ->setParameter('sid', $sourceId)
                    ->getQuery()->getSingleScalarResult();

                if ((int) $existing > 0) continue;

                $notif = new Notification();
                $notif->setUser($user);
                $notif->setTitle($title);
                $notif->setMessage($message);
                $notif->setType($isOverdue ? 'oil_change_overdue' : 'oil_change_due');
                $notif->setSourceType($sourceType);
                $notif->setSourceId($sourceId);
                $notif->setPriority($isOverdue ? 'HIGH' : 'MEDIUM');
                $notif->setDeepLink('/voiture/' . $voiture->getId() . '?tab=technical');
                $this->em->persist($notif);
            }
        }

        $this->em->flush();
    }

    // ── Data collectors ───────────────────────────────────────────────────────

    private function collectComplianceAlerts(): array
    {
        $alerts = [];

        foreach ($this->voitureRepo->findActive() as $voiture) {
            $label  = $voiture->getMarque() . ' ' . $voiture->getModele()
                    . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '');
            $status = $this->complianceService->getComplianceStatus($voiture);

            // 'doc' stores the stable key ('assurance'/'vignette'/'visite'), not a
            // display label -- buildHtml() translates it per recipient language.
            foreach (['assurance', 'vignette', 'visite'] as $key) {
                $info = $status[$key];
                $days = $info['daysRemaining'];
                // WARNING/CRITICAL/EXPIRED always alert; VALID only alerts within the
                // 90-day advance-notice window (ComplianceService doesn't flag WARNING
                // until 30 days out, so the 90/60-day reminder tiers need this on top).
                $inAdvanceWindow = $info['status'] === ComplianceService::VALID && $days !== null && $days <= 90;
                if (in_array($info['status'], [ComplianceService::WARNING, ComplianceService::CRITICAL, ComplianceService::EXPIRED], true) || $inAdvanceWindow) {
                    $alerts[] = [
                        'car'     => $label,
                        'doc'     => $key,
                        'status'  => $info['status'],
                        'days'    => $days,
                        'expires' => $info['expiresAt'],
                    ];
                }
            }
        }

        return $alerts;
    }

    private function collectOilAlerts(): array
    {
        $alerts = [];

        foreach ($this->voitureRepo->findActive() as $voiture) {
            $oil = $this->oilChangeService->getOilStatus($voiture);

            if (in_array($oil['status'], [OilChangeService::DUE_SOON, OilChangeService::OVERDUE], true)) {
                $alerts[] = [
                    'car'       => $voiture->getMarque() . ' ' . $voiture->getModele()
                                 . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : ''),
                    'status'    => $oil['status'],
                    'remaining' => $oil['remainingKm'],
                    'nextAt'    => $oil['nextOilChangeKm'],
                ];
            }
        }

        return $alerts;
    }

    private function collectCreditAlerts(): array
    {
        $alerts = [];

        foreach ($this->creditRepo->findActive() as $credit) {
            $today         = new \DateTimeImmutable('today');
            $paymentsCount = $credit->getPaiements()->count();
            $dureeMois     = $credit->getDureeMois() ?? 0;

            if ($paymentsCount >= $dureeMois) continue;

            $nextDue   = $credit->getDateDebut()->modify("+{$paymentsCount} months");
            $diff      = (int) $today->diff($nextDue)->days;
            $remaining = $nextDue >= $today ? $diff : -$diff;

            if ($remaining > 7) continue;

            $voiture = $credit->getVoiture();
            $label   = $voiture
                ? $voiture->getMarque() . ' ' . $voiture->getModele()
                : 'Credit #' . $credit->getId();

            $alerts[] = [
                'car'      => $label,
                'status'   => $remaining <= 0 ? 'OVERDUE' : 'DUE_SOON',
                'amount'   => number_format((float) $credit->getMensualite(), 2),
                'dueDate'  => $nextDue->format('d/m/Y'),
                'days'     => $remaining,
            ];
        }

        return $alerts;
    }

    private function collectVehicleCreditInstallmentAlerts(): array
    {
        $today  = new \DateTimeImmutable('today');
        $cutoff = $today->modify('+5 days');

        /** @var VehicleCreditInstallment[] $installments */
        $installments = $this->em->createQueryBuilder()
            ->select('i', 'vc', 'v')
            ->from(VehicleCreditInstallment::class, 'i')
            ->join('i.vehicleCredit', 'vc')
            ->join('vc.voiture', 'v')
            ->where("i.status NOT IN ('paid')")
            ->andWhere('i.dueDate <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();

        $alerts = [];
        foreach ($installments as $inst) {
            $vc      = $inst->getVehicleCredit();
            $voiture = $vc->getVoiture();
            $due     = \DateTimeImmutable::createFromInterface($inst->getDueDate());
            $daysLeft = (int) $today->diff($due)->format('%r%a');

            $carName = $voiture
                ? trim(($voiture->getMarque() ?? '') . ' ' . ($voiture->getModele() ?? ''))
                  . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '')
                : 'Véhicule';

            $alerts[] = [
                'car'         => $carName,
                'installment' => $inst->getInstallmentNumber(),
                'amount'      => number_format((float) $inst->getAmountDue(), 2),
                'dueDate'     => $due->format('d/m/Y'),
                'days'        => $daysLeft,
                'status'      => $daysLeft < 0 ? 'OVERDUE' : ($daysLeft === 0 ? 'DUE_TODAY' : 'DUE_SOON'),
            ];
        }

        return $alerts;
    }

    // ── HTML builder ──────────────────────────────────────────────────────────

    /**
     * All display strings for the daily report, per language. Keep keys
     * identical across all three so buildHtml() never has to branch on $lang
     * except for direction/alignment.
     */
    private function labels(string $lang): array
    {
        $all = [
            'en' => [
                'htmlLang' => 'en', 'dir' => 'ltr', 'align' => 'left',
                'title' => '🚗 AGOCAR — Daily Alert Report',
                'emailSubject' => 'AGOCAR Daily Report — %d alert(s) — %s',
                'generatedOn' => 'Generated on %s at 10:00 AM',
                'totalAlerts' => 'Total alerts', 'compliance' => 'Compliance', 'oilChanges' => 'Oil changes',
                'creditsOld' => 'Credits (old)', 'creditInstallments' => 'Credit installments',
                'sectionCompliance' => '🛡️ Document Compliance', 'sectionOil' => '🛢️ Oil Changes',
                'sectionCredits' => '💳 Credit Payments (legacy)', 'sectionVc' => '📅 Vehicle Credit Installments (≤ 5 days)',
                'vehicle' => 'Vehicle', 'document' => 'Document', 'status' => 'Status', 'expiresDays' => 'Expires / Days',
                'remainingKm' => 'Remaining km', 'nextServiceAt' => 'Next service at',
                'vehicleCredit' => 'Vehicle / Credit', 'dueDate' => 'Due date', 'amount' => 'Amount', 'amountDue' => 'Amount due',
                'badgeExpired' => 'EXPIRED', 'badgeCritical' => 'CRITICAL', 'badgeAdvance' => 'ADVANCE NOTICE',
                'badgeWarning' => 'WARNING', 'badgeOverdue' => 'OVERDUE', 'badgeDueSoon' => 'DUE SOON', 'badgeToday' => 'TODAY',
                'noCompliance' => '✅ No compliance issues.', 'noOil' => '✅ No oil change alerts.',
                'noCredits' => '✅ No legacy credit alerts.', 'noVc' => '✅ No upcoming credit installments in the next 5 days.',
                'daysOverdue' => '%dd overdue', 'dueToday' => 'today', 'dueIn' => 'in %dd', 'daysSuffix' => 'd',
                'docAssurance' => 'Insurance', 'docVignette' => 'Road tax (vignette)', 'docVisite' => 'Technical inspection',
                'footer' => 'This report is sent automatically every day at 10:00 AM by AGOCAR.<br>Do not reply to this email.',
            ],
            'fr' => [
                'htmlLang' => 'fr', 'dir' => 'ltr', 'align' => 'left',
                'title' => '🚗 AGOCAR — Rapport d\'alertes quotidien',
                'emailSubject' => 'Rapport quotidien AGOCAR — %d alerte(s) — %s',
                'generatedOn' => 'Généré le %s à 10h00',
                'totalAlerts' => 'Total des alertes', 'compliance' => 'Conformité', 'oilChanges' => 'Vidanges',
                'creditsOld' => 'Crédits (ancien)', 'creditInstallments' => 'Échéances de crédit',
                'sectionCompliance' => '🛡️ Conformité des documents', 'sectionOil' => '🛢️ Vidanges',
                'sectionCredits' => '💳 Paiements de crédit (ancien)', 'sectionVc' => '📅 Échéances de crédit véhicule (≤ 5 jours)',
                'vehicle' => 'Véhicule', 'document' => 'Document', 'status' => 'Statut', 'expiresDays' => 'Expire / Jours',
                'remainingKm' => 'Km restants', 'nextServiceAt' => 'Prochaine vidange à',
                'vehicleCredit' => 'Véhicule / Crédit', 'dueDate' => 'Date d\'échéance', 'amount' => 'Montant', 'amountDue' => 'Montant dû',
                'badgeExpired' => 'EXPIRÉ', 'badgeCritical' => 'CRITIQUE', 'badgeAdvance' => 'PRÉAVIS',
                'badgeWarning' => 'ATTENTION', 'badgeOverdue' => 'EN RETARD', 'badgeDueSoon' => 'BIENTÔT DÛ', 'badgeToday' => 'AUJOURD\'HUI',
                'noCompliance' => '✅ Aucun problème de conformité.', 'noOil' => '✅ Aucune alerte de vidange.',
                'noCredits' => '✅ Aucune alerte de crédit (ancien).', 'noVc' => '✅ Aucune échéance de crédit véhicule dans les 5 prochains jours.',
                'daysOverdue' => '%dj de retard', 'dueToday' => 'aujourd\'hui', 'dueIn' => 'dans %dj', 'daysSuffix' => 'j',
                'docAssurance' => 'Assurance', 'docVignette' => 'Vignette', 'docVisite' => 'Visite technique',
                'footer' => 'Ce rapport est envoyé automatiquement chaque jour à 10h00 par AGOCAR.<br>Ne pas répondre à cet email.',
            ],
            'ar' => [
                'htmlLang' => 'ar', 'dir' => 'rtl', 'align' => 'right',
                'title' => '🚗 AGOCAR — تقرير التنبيهات اليومي',
                'emailSubject' => 'تقرير AGOCAR اليومي — %d تنبيه(ات) — %s',
                'generatedOn' => 'تم الإنشاء في %s الساعة 10:00 صباحاً',
                'totalAlerts' => 'إجمالي التنبيهات', 'compliance' => 'الامتثال', 'oilChanges' => 'تغيير الزيت',
                'creditsOld' => 'القروض (قديم)', 'creditInstallments' => 'أقساط القروض',
                'sectionCompliance' => '🛡️ امتثال الوثائق', 'sectionOil' => '🛢️ تغيير الزيت',
                'sectionCredits' => '💳 مدفوعات القروض (قديم)', 'sectionVc' => '📅 أقساط قروض السيارات (≤ 5 أيام)',
                'vehicle' => 'السيارة', 'document' => 'الوثيقة', 'status' => 'الحالة', 'expiresDays' => 'تنتهي / أيام',
                'remainingKm' => 'الكيلومترات المتبقية', 'nextServiceAt' => 'الصيانة القادمة عند',
                'vehicleCredit' => 'السيارة / القرض', 'dueDate' => 'تاريخ الاستحقاق', 'amount' => 'المبلغ', 'amountDue' => 'المبلغ المستحق',
                'badgeExpired' => 'منتهي الصلاحية', 'badgeCritical' => 'حرج', 'badgeAdvance' => 'إشعار مسبق',
                'badgeWarning' => 'تحذير', 'badgeOverdue' => 'متأخر', 'badgeDueSoon' => 'قريب الاستحقاق', 'badgeToday' => 'اليوم',
                'noCompliance' => '✅ لا توجد مشاكل في الامتثال.', 'noOil' => '✅ لا توجد تنبيهات لتغيير الزيت.',
                'noCredits' => '✅ لا توجد تنبيهات قروض قديمة.', 'noVc' => '✅ لا توجد أقساط قروض مستحقة خلال 5 أيام القادمة.',
                'daysOverdue' => 'متأخر %d يوم', 'dueToday' => 'اليوم', 'dueIn' => 'خلال %d يوم', 'daysSuffix' => 'يوم',
                'docAssurance' => 'التأمين', 'docVignette' => 'الضريبة السنوية', 'docVisite' => 'الفحص التقني',
                'footer' => 'يتم إرسال هذا التقرير تلقائياً كل يوم الساعة 10:00 صباحاً بواسطة AGOCAR.<br>الرجاء عدم الرد على هذا البريد الإلكتروني.',
            ],
        ];

        return $all[$lang] ?? $all['fr'];
    }

    private function buildHtml(array $compliance, array $oil, array $credits, array $vcInstallments, int $total, string $lang = 'fr'): string
    {
        $t    = $this->labels($lang);
        $date = date('d/m/Y');
        $docLabelKeys = ['assurance' => 'docAssurance', 'vignette' => 'docVignette', 'visite' => 'docVisite'];

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="{$t['htmlLang']}" dir="{$t['dir']}">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
          body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #333; direction: {$t['dir']}; }
          .wrapper { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
          .header { background: #1a365d; color: #fff; padding: 24px 28px; text-align: {$t['align']}; }
          .header h1 { margin: 0; font-size: 22px; }
          .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.85; }
          .summary-bar { background: #2b6cb0; color: #fff; padding: 10px 28px; font-size: 13px; text-align: {$t['align']}; }
          .content { padding: 24px 28px; }
          .section-title { font-size: 15px; font-weight: bold; margin: 20px 0 10px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; color: #2d3748; text-align: {$t['align']}; }
          .section-title:first-child { margin-top: 0; }
          table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 4px; table-layout: fixed; }
          th { background: #edf2f7; color: #4a5568; text-align: {$t['align']}; padding: 8px 10px; font-weight: 600; word-break: break-word; }
          td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; word-break: break-word; text-align: {$t['align']}; }
          tr:last-child td { border-bottom: none; }
          .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; white-space: nowrap; }
          .badge-expired  { background: #fff5f5; color: #c53030; border: 1px solid #fc8181; }
          .badge-critical { background: #fffaf0; color: #c05621; border: 1px solid #f6ad55; }
          .badge-warning  { background: #fffff0; color: #744210; border: 1px solid #f6e05e; }
          .badge-advance  { background: #ebf8ff; color: #2c5282; border: 1px solid #90cdf4; }
          .badge-overdue  { background: #fff5f5; color: #c53030; border: 1px solid #fc8181; }
          .badge-duesoon  { background: #fffaf0; color: #c05621; border: 1px solid #f6ad55; }
          .footer { background: #f7fafc; padding: 16px 28px; font-size: 11px; color: #a0aec0; text-align: center; border-top: 1px solid #e2e8f0; }
          .no-issues { color: #718096; font-size: 13px; font-style: italic; padding: 6px 0; text-align: {$t['align']}; }
          @media (max-width: 480px) {
            body { padding: 8px; }
            .content { padding: 14px 12px; }
            .header { padding: 16px 14px; }
            .summary-bar { padding: 8px 14px; font-size: 12px; }
            table { font-size: 11px; }
            th, td { padding: 6px 5px; }
            .badge { padding: 1px 5px; font-size: 9px; white-space: normal; line-height: 1.3; }
            td small { display: block; font-size: 9px; }
          }
        </style>
        </head>
        <body>
        <div class="wrapper">
          <div class="header">
            <h1>{$t['title']}</h1>
            <p>{$this->fmt($t['generatedOn'], $date)}</p>
          </div>
          <div class="summary-bar">
            {$t['totalAlerts']}: <strong>$total</strong>
            &nbsp;·&nbsp; {$t['compliance']}: <strong>{$this->count($compliance)}</strong>
            &nbsp;·&nbsp; {$t['oilChanges']}: <strong>{$this->count($oil)}</strong>
            &nbsp;·&nbsp; {$t['creditsOld']}: <strong>{$this->count($credits)}</strong>
            &nbsp;·&nbsp; {$t['creditInstallments']}: <strong>{$this->count($vcInstallments)}</strong>
          </div>
          <div class="content">
        HTML;

        // ── Compliance section
        $html .= "<div class=\"section-title\">{$t['sectionCompliance']}</div>";
        if ($compliance) {
            $html .= "<table><tr><th>{$t['vehicle']}</th><th>{$t['document']}</th><th>{$t['status']}</th><th>{$t['expiresDays']}</th></tr>";
            foreach ($compliance as $a) {
                $badge = match($a['status']) {
                    ComplianceService::EXPIRED  => "<span class=\"badge badge-expired\">{$t['badgeExpired']}</span>",
                    ComplianceService::CRITICAL => "<span class=\"badge badge-critical\">{$t['badgeCritical']}</span>",
                    ComplianceService::VALID    => "<span class=\"badge badge-advance\">{$t['badgeAdvance']}</span>",
                    default                     => "<span class=\"badge badge-warning\">{$t['badgeWarning']}</span>",
                };
                $days   = $a['days'] !== null ? htmlspecialchars((string) $a['days']) . $t['daysSuffix'] : '—';
                $exp    = $a['expires'] ?? '—';
                $doc    = $t[$docLabelKeys[$a['doc']] ?? ''] ?? $a['doc'];
                $html .= "<tr><td>{$a['car']}</td><td>$doc</td><td>$badge</td><td>$exp ($days)</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= "<p class=\"no-issues\">{$t['noCompliance']}</p>";
        }

        // ── Oil section
        $html .= "<div class=\"section-title\">{$t['sectionOil']}</div>";
        if ($oil) {
            $html .= "<table><tr><th>{$t['vehicle']}</th><th>{$t['status']}</th><th>{$t['remainingKm']}</th><th>{$t['nextServiceAt']}</th></tr>";
            foreach ($oil as $a) {
                $badge = $a['status'] === OilChangeService::OVERDUE
                    ? "<span class=\"badge badge-overdue\">{$t['badgeOverdue']}</span>"
                    : "<span class=\"badge badge-duesoon\">{$t['badgeDueSoon']}</span>";
                $rem    = htmlspecialchars((string) $a['remaining']);
                $nextAt = htmlspecialchars((string) ($a['nextAt'] ?? '—'));
                $html .= "<tr><td>{$a['car']}</td><td>$badge</td><td>{$rem} km</td><td>{$nextAt} km</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= "<p class=\"no-issues\">{$t['noOil']}</p>";
        }

        // ── Legacy credits section
        $html .= "<div class=\"section-title\">{$t['sectionCredits']}</div>";
        if ($credits) {
            $html .= "<table><tr><th>{$t['vehicleCredit']}</th><th>{$t['status']}</th><th>{$t['dueDate']}</th><th>{$t['amount']}</th></tr>";
            foreach ($credits as $a) {
                $badge = $a['status'] === 'OVERDUE'
                    ? "<span class=\"badge badge-overdue\">{$t['badgeOverdue']}</span>"
                    : "<span class=\"badge badge-duesoon\">{$t['badgeDueSoon']}</span>";
                $html .= "<tr><td>{$a['car']}</td><td>$badge</td><td>{$a['dueDate']}</td><td>{$a['amount']} MAD</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= "<p class=\"no-issues\">{$t['noCredits']}</p>";
        }

        // ── VehicleCredit installments section
        $html .= "<div class=\"section-title\">{$t['sectionVc']}</div>";
        if ($vcInstallments) {
            $html .= "<table><tr><th>{$t['vehicle']}</th><th>#</th><th>{$t['status']}</th><th>{$t['dueDate']}</th><th>{$t['amountDue']}</th></tr>";
            foreach ($vcInstallments as $a) {
                $badge = match($a['status']) {
                    'OVERDUE'   => "<span class=\"badge badge-overdue\">{$t['badgeOverdue']}</span>",
                    'DUE_TODAY' => "<span class=\"badge badge-critical\">{$t['badgeToday']}</span>",
                    default     => "<span class=\"badge badge-duesoon\">{$t['badgeDueSoon']}</span>",
                };
                $daysLabel = $a['days'] < 0
                    ? $this->fmt($t['daysOverdue'], abs($a['days']))
                    : ($a['days'] === 0 ? $t['dueToday'] : $this->fmt($t['dueIn'], $a['days']));
                $html .= "<tr><td>{$a['car']}</td><td>#{$a['installment']}</td><td>$badge</td><td>{$a['dueDate']} <small style=\"color:#718096\">($daysLabel)</small></td><td>{$a['amount']} MAD</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= "<p class=\"no-issues\">{$t['noVc']}</p>";
        }

        $html .= <<<HTML
          </div>
          <div class="footer">
            {$t['footer']}
          </div>
        </div>
        </body>
        </html>
        HTML;

        return $html;
    }

    private function count(array $arr): int
    {
        return count($arr);
    }

    private function fmt(string $template, int|string ...$values): string
    {
        return sprintf($template, ...$values);
    }
}
