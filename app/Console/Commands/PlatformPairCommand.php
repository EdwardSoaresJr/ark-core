<?php

namespace App\Console\Commands;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\PlatformPairingClient;
use Illuminate\Console\Command;
use Throwable;

class PlatformPairCommand extends Command
{
    protected $signature = 'ark:platform-pair
        {action : start|claim|status}
        {--platform-url= : ARK Platform base URL}
        {--pairing-public-id= : Pairing public id for claim}';

    protected $description = 'Core↔Platform pairing (start / claim / status)';

    public function handle(PlatformPairingClient $client): int
    {
        $action = strtolower((string) $this->argument('action'));
        $url = $this->option('platform-url');
        $url = is_string($url) && $url !== '' ? $url : null;

        try {
            return match ($action) {
                'start' => $this->start($client, $url),
                'claim' => $this->claim($client, $url),
                'status' => $this->status($client),
                default => $this->invalid($action),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function start(PlatformPairingClient $client, ?string $url): int
    {
        $result = $client->start($url);
        $this->table(
            ['Field', 'Value'],
            [
                ['installation_uuid', $result['installation_uuid']],
                ['pairing_public_id', $result['pairing_public_id']],
                ['pairing_code', $result['pairing_code']],
                ['expires_at', $result['expires_at']],
            ],
        );
        $this->info('Approve this code in ARK Platform, then run: php artisan ark:platform-pair claim');

        return self::SUCCESS;
    }

    private function claim(PlatformPairingClient $client, ?string $url): int
    {
        $publicId = $this->option('pairing-public-id');
        $publicId = is_string($publicId) && $publicId !== '' ? $publicId : null;
        $result = $client->claim($publicId, $url);
        $this->info('Connected. shop_public_id='.($result['shop_public_id'] ?? ''));

        return self::SUCCESS;
    }

    private function status(PlatformPairingClient $client): int
    {
        $cloud = PlatformConnection::current();
        $soft = $client->statusSoft();
        $this->table(
            ['Field', 'Value'],
            [
                ['installation_uuid', InstallationIdentity::uuid()],
                ['local_status', $cloud->status() ?? ''],
                ['connected', $cloud->isConnected() ? 'yes' : 'no'],
                ['shop_public_id', $cloud->shopPublicId() ?? ''],
                ['base_url', $cloud->baseUrl()],
                ['platform_reachable', $soft['ok'] ? 'yes' : 'no'],
                ['platform_status', $soft['status'] ?? ''],
                ['error', $soft['error'] ?? ''],
            ],
        );

        return self::SUCCESS;
    }

    private function invalid(string $action): int
    {
        $this->error("Unknown action [{$action}]. Use start|claim|status.");

        return self::FAILURE;
    }
}
