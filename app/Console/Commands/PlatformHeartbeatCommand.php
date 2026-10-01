<?php

namespace App\Console\Commands;

use App\Ark\Platform\ArkBoxHeartbeatClient;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Console\Command;

final class PlatformHeartbeatCommand extends Command
{
    protected $signature = 'ark:platform-heartbeat';

    protected $description = 'Signed ARK Box check-in with ARK Platform';

    public function handle(ArkBoxHeartbeatClient $client): int
    {
        if (! PlatformConnection::current()->isConnected()) {
            return self::SUCCESS;
        }

        $result = $client->send();
        if (($result['ok'] ?? false) !== true) {
            $this->error($result['message'] ?? 'ARK Platform heartbeat failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
