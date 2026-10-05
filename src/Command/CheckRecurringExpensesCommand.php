<?php

namespace App\Command;

use App\Entity\Depense;
use App\Entity\Notification;
use App\Entity\RecurringExpenseTemplate;
use App\Repository\DepenseRepository;
use App\Repository\RecurringExpenseTemplateRepository;
use App\Repository\UtilisateurRepository;
use App\Enum\StatusEnum;
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
 * Run daily via system cron:
 *   0 8 * * * cd /path/to/autoloc && php bin/console app:check-recurring-expenses >> var/log/recurring.log 2>&1
 */
#[AsCommand(
    name: 'app:check-recurring-expenses',
    description: 'Auto-generate pending entries for recurring bureau expenses and notify managers/admins',
)]
class CheckRecurringExpensesCommand extends Command
{
    private const TYPE_LABELS = [
        'loyer'        => 'Loyer',
        'salaire'      => 'Salaire',
        'telephone'    => 'Téléphone',
        'electricite'  => 'Électricité',
        'eau'          => 'Eau',
        'internet'     => 'Internet',
        'vignette'     => 'Vignette',
    ];

    public function __construct(
        private RecurringExpenseTemplateRepository $templateRepo,
        private DepenseRepository                  $depenseRepo,
        private UtilisateurRepository              $utilisateurRepo,
        private EntityManagerInterface             $em,
        private MailerInterface                    $mailer,
        private string                             $senderEmail,
        private string                             $recipientEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Generate entries regardless of date (for testing)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $today = new \DateTimeImmutable('today');
        $generated = 0;

        $templates = $this->templateRepo->findAllActive();

        foreach ($templates as $template) {
            if (!$this->shouldTriggerToday($template, $today, $force)) {
                continue;
            }

            [$periodMonth, $periodYear] = $this->currentPeriod($template, $today);

            // Duplicate check
            if ($this->entryExists($template, $periodMonth, $periodYear)) {
                $io->note(sprintf('Skip %s/%s %s — already exists', $periodMonth, $periodYear, $template->getTypeDepense()));
                continue;
            }

            $depense = $this->createPendingEntry($template, $periodMonth, $periodYear, $today);
            $this->em->persist($depense);
            $this->em->flush(); // flush to get id

            $this->createNotifications($template, $depense, $periodMonth, $periodYear);
            $this->sendEmail($template, $periodMonth, $periodYear);

            $depense->setNotificationSentAt(new \DateTimeImmutable());
            $this->em->flush();

            $label = self::TYPE_LABELS[$template->getTypeDepense()] ?? $template->getTypeDepense();
            $io->success(sprintf('Generated: %s — %s %d/%d', $template->getBureau()?->getNom(), $label, $periodMonth ?? 0, $periodYear));
            $generated++;
        }

        $io->info(sprintf('%d entries generated.', $generated));
        return Command::SUCCESS;
    }

    private function shouldTriggerToday(RecurringExpenseTemplate $t, \DateTimeImmutable $today, bool $force): bool
    {
        if ($force) return true;

        if ($t->getFrequency() === 'monthly') {
            // Trigger when 10 days or fewer remain in the current month
            $lastDay   = new \DateTimeImmutable('last day of this month');
            $daysLeft  = (int)$today->diff($lastDay)->days;
            return $daysLeft <= 10;
        }

        // yearly: trigger on December 1st (≈ 30 days before year end)
        return $today->format('m-d') === '12-01';
    }

    private function currentPeriod(RecurringExpenseTemplate $t, \DateTimeImmutable $today): array
    {
        if ($t->getFrequency() === 'monthly') {
            // Generate for NEXT month (the one that's coming)
            $next = $today->modify('first day of next month');
            return [(int)$next->format('m'), (int)$next->format('Y')];
        }
        // yearly: generate for next year
        return [null, (int)$today->format('Y') + 1];
    }

    private function entryExists(RecurringExpenseTemplate $t, ?int $month, int $year): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Depense::class, 'd')
            ->where('d.recurringTemplate = :t')
            ->andWhere('d.periodYear = :year')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('t', $t)
            ->setParameter('year', $year);

        if ($month !== null) {
            $qb->andWhere('d.periodMonth = :month')->setParameter('month', $month);
        }

        return (int)$qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function createPendingEntry(
        RecurringExpenseTemplate $t,
        ?int $month,
        int $year,
        \DateTimeImmutable $today
    ): Depense {
        $amount  = $t->getPriceType() === 'fixed' ? ($t->getFixedAmount() ?? '0') : '0';
        $dateStr = $month ? sprintf('%d-%02d-01', $year, $month) : sprintf('%d-01-01', $year);

        $depense = new Depense();
        $depense->setTypeDepense($t->getTypeDepense());
        $depense->setMontant($amount);
        $depense->setStatut(StatusEnum::PENDING);
        $depense->setDateDebut(new \DateTimeImmutable($dateStr));
        $depense->setBureau($t->getBureau());
        $depense->setCreeAu($today);
        $depense->setIsAutoGenerated(true);
        $depense->setRecurringTemplate($t);
        $depense->setPeriodMonth($month);
        $depense->setPeriodYear($year);

        return $depense;
    }

    private function createNotifications(RecurringExpenseTemplate $t, Depense $d, ?int $month, int $year): void
    {
        $label      = self::TYPE_LABELS[$t->getTypeDepense()] ?? $t->getTypeDepense();
        $bureauName = $t->getBureau()?->getNom() ?? '';
        $period     = $month ? sprintf('%02d/%d', $month, $year) : (string)$year;

        $adminsAndManagers = $this->utilisateurRepo->createQueryBuilder('u')
            ->where('u.roles LIKE :admin OR u.roles LIKE :manager')
            ->setParameter('admin',   '%ROLE_ADMIN%')
            ->setParameter('manager', '%ROLE_MANAGER%')
            ->getQuery()
            ->getResult();

        foreach ($adminsAndManagers as $user) {
            // Avoid duplicate notification for same expense
            $exists = $this->em->createQueryBuilder()
                ->select('COUNT(n.id)')
                ->from(Notification::class, 'n')
                ->where('n.user = :u AND n.sourceType = :st AND n.sourceId = :sid')
                ->setParameter('u',   $user)
                ->setParameter('st',  'recurring_expense')
                ->setParameter('sid', $d->getId())
                ->getQuery()
                ->getSingleScalarResult();

            if ($exists > 0) continue;

            $notif = new Notification();
            $notif->setUser($user);
            $notif->setTitle(sprintf('%s — %s', $label, $bureauName));
            $notif->setMessage(sprintf('La dépense %s pour %s (%s) est due dans moins de 10 jours.', $label, $bureauName, $period));
            $notif->setType('recurring_expense_due');
            $notif->setSourceType('recurring_expense');
            $notif->setSourceId($d->getId());
            $notif->setPriority('HIGH');
            $notif->setDeepLink('/bureau-expenses');
            $this->em->persist($notif);
        }

        $this->em->flush();
    }

    private function sendEmail(RecurringExpenseTemplate $t, ?int $month, int $year): void
    {
        $label      = self::TYPE_LABELS[$t->getTypeDepense()] ?? $t->getTypeDepense();
        $bureauName = $t->getBureau()?->getNom() ?? '';
        $period     = $month ? sprintf('%02d/%d', $month, $year) : (string)$year;
        $frequency  = $t->getFrequency() === 'monthly' ? 'mensuelle' : 'annuelle';
        $amount     = $t->getPriceType() === 'fixed'
            ? number_format((float)($t->getFixedAmount() ?? 0), 2, ',', ' ') . ' MAD'
            : 'à définir';

        $html = sprintf('
            <h2>Rappel dépense récurrente — %s</h2>
            <p>La dépense <strong>%s</strong> (%s) pour le bureau <strong>%s</strong> est prévue pour la période <strong>%s</strong>.</p>
            <p>Montant : <strong>%s</strong></p>
            <p>Une ligne en attente a été créée automatiquement dans Bureau Expenses.</p>
            <p style="color:#b45309">Veuillez la confirmer et la marquer comme payée dès que possible.</p>
        ', $label, $label, $frequency, $bureauName, $period, $amount);

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($this->recipientEmail)
            ->subject(sprintf('[AutoLoc] Dépense récurrente due — %s %s (%s)', $label, $bureauName, $period))
            ->html($html);

        try {
            $this->mailer->send($email);
        } catch (\Throwable) {
            // Email failure should not stop entry generation
        }
    }
}
