<?php

namespace App\Console\Commands;

use App\Ark\Runtime\Identity\Oidc\OidcClient;
use App\Ark\Runtime\Identity\Oidc\OidcProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OidcSeedSpikeCommand extends Command
{
    protected $signature = 'ark:oidc:spike:seed {--redirect-uri=https://learn.demo-auto.test/oidc/callback}';

    protected $description = 'Seed staging OIDC client for ARKademy (spike only)';

    public function handle(): int
    {
        $plainSecret = Str::random(48);

        OidcClient::query()->updateOrCreate(
            ['client_id' => 'arkademy'],
            [
                'name' => 'ARKademy (BookStack)',
                'client_secret' => $plainSecret,
                'redirect_uris' => [$this->option('redirect-uri')],
                'required_product' => OidcProduct::Arkademy->value,
                'is_confidential' => true,
            ],
        );

        $this->info('OIDC spike client seeded [client_id=arkademy].');
        $this->line('Redirect URI: '.$this->option('redirect-uri'));
        $this->warn('Client secret (store in BookStack env only): '.$plainSecret);

        return self::SUCCESS;
    }
}
