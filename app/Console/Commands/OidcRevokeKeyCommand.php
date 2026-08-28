<?php

namespace App\Console\Commands;

use App\Ark\Runtime\Identity\Oidc\OidcKeyRepository;
use Illuminate\Console\Command;

class OidcRevokeKeyCommand extends Command
{
    protected $signature = 'ark:oidc:keys:revoke {kid : Key ID to revoke}';

    protected $description = 'Revoke an OIDC signing key and remove it from JWKS';

    public function handle(OidcKeyRepository $keys, string $kid): int
    {
        $keys->revokeKey($kid);

        $this->info("OIDC signing key revoked [kid={$kid}].");

        return self::SUCCESS;
    }
}
