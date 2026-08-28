<?php

namespace App\Console\Commands;

use App\Ark\Operations\Messaging\Messenger\LegacyMetaMessengerPlatformCredentialsResolver;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerPlatformConfiguration;
use Illuminate\Console\Command;

class ExportMessengerPlatformEnvCommand extends Command
{
    protected $signature = 'ark:messenger:export-platform-env {--show-secrets : Print credential values (sensitive)}';

    protected $description = 'Export Meta Messenger platform env keys from config + legacy shop fallback';

    public function handle(LegacyMetaMessengerPlatformCredentialsResolver $legacy): int
    {
        $platform = MetaMessengerPlatformConfiguration::current($legacy);

        $rows = [
            ['META_MESSENGER_APP_ID', filled(config('services.meta_messenger.app_id')) ? 'env' : 'missing', (string) (config('services.meta_messenger.app_id') ?? '')],
            ['META_MESSENGER_APP_SECRET', $platform->secretSource(), (string) ($platform->appSecret() ?? '')],
            ['META_MESSENGER_VERIFY_TOKEN', $platform->verifySource(), (string) ($platform->verifyToken() ?? '')],
            ['META_MESSENGER_GRAPH_VERSION', filled(config('services.meta_messenger.graph_version')) ? 'env' : 'default', $platform->graphVersion()],
        ];

        if (! $this->option('show-secrets')) {
            $this->table(['Key', 'Source', 'Present'], array_map(
                fn (array $row): array => [$row[0], $row[1], $row[2] !== '' ? 'yes' : 'no'],
                $rows,
            ));
            $this->warn('Re-run with --show-secrets to print values for Coolify paste.');

            return self::SUCCESS;
        }

        $this->warn('Printing secrets. Do not leave this in shell history on shared machines.');

        foreach ($rows as [$key, $source, $value]) {
            if ($value === '') {
                $this->line("# {$key}=  # missing ({$source})");

                continue;
            }

            $this->line("{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
