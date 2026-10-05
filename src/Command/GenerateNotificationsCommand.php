<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Not currently triggered automatically anywhere — requires a host-level
 * crontab entry (no in-app scheduler/worker is configured in this project).
 * Run more often than the daily email (app:send-daily-report) since this
 * feeds the in-app notification bell/inbox, which users expect to be current:
 *
 *   0 * * * * cd /path/to/autoloc && php bin/console app:generate-notifications >> var/log/notifications.log 2>&1
 */
#[AsCommand(
    name: 'app:generate-notifications',
    description: 'Sync in-app notifications for compliance, oil changes, and credit installments — requires a crontab entry, see class docblock',
)]
class GenerateNotificationsCommand extends Command
{
    public function __construct(private NotificationService $notificationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating notifications');

        $this->notificationService->syncAll();

        $io->success('Notifications synced successfully.');
        return Command::SUCCESS;
    }
}
