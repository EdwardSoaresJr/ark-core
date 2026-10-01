<?php

namespace App\Console\Commands;

use App\Ark\Install\InstallationState;
use App\Ark\Install\PendingInstallPayload;
use Illuminate\Console\Command;

/**
 * Clears interrupted IN_PROGRESS only. Never unlocks a completed install.
 */
class ArkInstallRecoverCommand extends Command
{
    protected $signature = 'ark:install-recover {--force : Required confirmation}';

    protected $description = 'Recover a failed/interrupted first-run (IN_PROGRESS only). Never clears INSTALLED.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force.');

            return self::FAILURE;
        }

        if (InstallationState::isInstalled()) {
            $this->error('ARK is installed. This command will not unlock setup.');

            return self::FAILURE;
        }

        if (! InstallationState::isInProgress()) {
            $this->info('Nothing to recover (status='.InstallationState::status().').');

            return self::SUCCESS;
        }

        InstallationState::recoverInterruptedProgress();
        PendingInstallPayload::clear();
        $this->info('Installation progress cleared. Visit /setup to continue.');

        return self::SUCCESS;
    }
}
