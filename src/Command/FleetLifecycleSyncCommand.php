<?php

namespace App\Command;

use App\Fleet\FleetLifecycleManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Nightly batch sync of all vehicle lifecycle states.
 *
 * Usage:
 *   php bin/console fleet:lifecycle:sync
 *   php bin/console fleet:lifecycle:sync --bureau=1
 *
 * Add to crontab (runs every night at 00:05):
 *   5 0 * * * /path/to/php /path/to/project/bin/console fleet:lifecycle:sync >> /var/log/fleet_sync.log 2>&1
 */
#[AsCommand(
    name:        'fleet:lifecycle:sync',
    description: 'Recomputes and persists lifecycle states for all active vehicles.',
)]
class FleetLifecycleSyncCommand extends Command
{
    public function __construct(
        private readonly FleetLifecycleManager $flm,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'bureau',
            'b',
            InputOption::VALUE_OPTIONAL,
            'Restrict sync to a specific bureau ID',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $bureauId = $input->getOption('bureau') !== null
            ? (int) $input->getOption('bureau')
            : null;

        $io->title('Fleet Lifecycle Sync');

        if ($bureauId !== null) {
            $io->note(sprintf('Restricting sync to bureau #%d', $bureauId));
        }

        $start   = microtime(true);
        $changed = $this->flm->syncAll($bureauId);
        $elapsed = round(microtime(true) - $start, 2);

        if ($changed > 0) {
            $io->success(sprintf('%d vehicle(s) updated in %.2f seconds.', $changed, $elapsed));
        } else {
            $io->info(sprintf('All vehicle states are already up to date (%.2f seconds).', $elapsed));
        }

        return Command::SUCCESS;
    }
}
