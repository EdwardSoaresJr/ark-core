<?php

namespace App\Console\Commands;

use App\Ark\Install\InstallationState;
use Illuminate\Console\Command;

class ArkInstallStatusCommand extends Command
{
    protected $signature = 'ark:install-status';

    protected $description = 'Show ARK first-run installation status (file-backed; no secrets).';

    public function handle(): int
    {
        $state = InstallationState::read();
        $this->line('status='.$state['status']);
        $this->line('checkpoint='.($state['checkpoint'] ?? ''));
        $this->line('updated_at='.($state['updated_at'] ?? ''));
        $this->line('state_file='.InstallationState::path());

        return self::SUCCESS;
    }
}
