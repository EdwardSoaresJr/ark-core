<?php

namespace App\Console\Commands;

use App\Ark\Install\InstallationState;
use Illuminate\Console\Command;

/**
 * Container lifecycle and operators query first-run authority here.
 *
 * InstallationState is file-backed under storage — no DB schema required.
 * Exit codes for --check-installed:
 *   0 = installed
 *   1 = not installed (or in_progress / missing marker)
 *   2 = check failed (callers must not mutate the database)
 *
 * Use Symfony/Laravel --quiet (-q) to suppress human-readable lines.
 * Do not redefine --quiet on this command (conflicts with the framework option).
 */
class ArkInstallStatusCommand extends Command
{
    protected $signature = 'ark:install-status
                            {--check-installed : Exit 0 when installed, 1 otherwise (no secrets)}';

    protected $description = 'Show ARK first-run installation status (file-backed; no secrets).';

    public function handle(): int
    {
        try {
            $state = InstallationState::read();
            $installed = InstallationState::isInstalled();

            if (! $this->output->isQuiet()) {
                $this->line('status='.$state['status']);
                $this->line('checkpoint='.($state['checkpoint'] ?? ''));
                $this->line('updated_at='.($state['updated_at'] ?? ''));
                $this->line('installed='.($installed ? 'yes' : 'no'));
                $this->line('state_file='.InstallationState::path());
            }

            if ($this->option('check-installed')) {
                return $installed ? 0 : 1;
            }

            return self::SUCCESS;
        } catch (\Throwable) {
            if (! $this->output->isQuiet()) {
                $this->error('status=error');
            }

            return 2;
        }
    }
}
