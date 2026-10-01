<?php

namespace App\Console\Commands;

use App\Ark\Runtime\Identity\Oidc\OidcKeyRepository;
use Illuminate\Console\Command;

class OidcRotateKeyCommand extends Command
{
    protected $signature = 'ark:oidc:keys:rotate';

    protected $description = 'Rotate the active OIDC signing key (publish overlap via JWKS)';

    public function handle(OidcKeyRepository $keys): int
    {
        $key = $keys->rotateKeyPair();

        $this->info("OIDC signing key rotated [kid={$key->kid}]. Previous keys remain published until revoked.");

        return self::SUCCESS;
    }
}
