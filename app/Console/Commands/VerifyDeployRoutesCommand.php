<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class VerifyDeployRoutesCommand extends Command
{
    protected $signature = 'ark:deploy:verify-routes';

    protected $description = 'Verify route/config cache builds cleanly (run in CI or before production deploy)';

    public function handle(): int
    {
        $steps = [
            'optimize:clear' => 'Clearing cached bootstrap files',
            'route:cache' => 'Caching routes',
            'config:cache' => 'Caching config',
        ];

        foreach ($steps as $command => $label) {
            $this->components->task($label, function () use ($command): bool {
                return Artisan::call($command, [], $this->output) === self::SUCCESS;
            });
        }

        $this->components->task('Listing routes', function (): bool {
            return Artisan::call('route:list', [], $this->output) === self::SUCCESS;
        });

        $this->newLine();
        $this->info('Deploy route verification passed.');

        return self::SUCCESS;
    }
}
