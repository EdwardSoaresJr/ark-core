<?php

namespace App\Console\Commands;

use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\LivingDemoSeeder;
use Illuminate\Console\Command;

class LivingDemoResetCommand extends Command
{
    protected $signature = 'ark:living-demo:reset';

    protected $description = 'Reset the Living Demo busy Tuesday schedule (local/testing only).';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->components->error('Living Demo reset is only available in local or testing.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => ArkAuthorizationSeeder::class, '--no-interaction' => true]);
        $this->call('db:seed', ['--class' => LivingDemoSeeder::class, '--no-interaction' => true]);

        $this->components->info('Living Demo reset — Schedule should show a busy Tuesday.');

        return self::SUCCESS;
    }
}
