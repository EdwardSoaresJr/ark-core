<?php

namespace App\Console\Commands;

use Database\Seeders\CloudScreenshotStageSeeder;
use Illuminate\Console\Command;

class CloudScreenshotStageCommand extends Command
{
    protected $signature = 'ark:cloud:stage-screenshots';

    protected $description = 'Stage fictional high-RO shop data for Cloud marketing screenshots (local/testing only).';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->components->error('Cloud screenshot staging is only available in local or testing.');

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => CloudScreenshotStageSeeder::class,
            '--no-interaction' => true,
        ]);

        $this->components->info('Cloud screenshot stage ready.');
        $this->line('  Hero RO #'.CloudScreenshotStageSeeder::HERO_SHOP_NUMBER.' · Sarah Mitchell · 2018 Honda Odyssey');
        $this->line('  Staff names: Alex Rivera · Marcus Hale · Jordan Lee');
        $this->line('  Login: admin@ark.test / password (admin avoids comms attention gate)');
        $this->line('  URL: '.rtrim((string) config('app.url'), '/').'/app/repair-orders/'.CloudScreenshotStageSeeder::HERO_SHOP_NUMBER);

        return self::SUCCESS;
    }
}
