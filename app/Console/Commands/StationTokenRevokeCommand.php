<?php

namespace App\Console\Commands;

use App\Ark\Station\StationDeviceToken;
use Illuminate\Console\Command;

final class StationTokenRevokeCommand extends Command
{
    protected $signature = 'station:token-revoke {id : station_device_tokens.id}';

    protected $description = 'Revoke a Front Counter glass device token';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $token = StationDeviceToken::query()->find($id);
        if ($token === null) {
            $this->error('Token not found.');

            return self::FAILURE;
        }

        $token->revoke();
        $this->info('Revoked '.$token->auditLabel());

        return self::SUCCESS;
    }
}
