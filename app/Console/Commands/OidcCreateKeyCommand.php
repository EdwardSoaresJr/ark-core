<?php

namespace App\Console\Commands;

use App\Ark\Runtime\Identity\Oidc\OidcKeyRepository;
use Illuminate\Console\Command;

class OidcCreateKeyCommand extends Command
{
    protected $signature = 'ark:oidc:keys:create';

    protected $description = 'Create the active OIDC RS256 signing keypair';

    public function handle(OidcKeyRepository $keys): int
    {
        $key = $keys->createKeyPair();

        $this->info("OIDC signing key created [kid={$key->kid}].");

        return self::SUCCESS;
    }
}
