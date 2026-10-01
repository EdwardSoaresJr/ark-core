<?php

namespace App\Console\Commands;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Console\Command;

class ImportIntegrationCredentialsFromEnv extends Command
{
    protected $signature = 'integrations:import-env
                            {--env-file= : Path to .env file (defaults to shared .env when present)}
                            {--clear-env : Blank imported keys in the .env file after a successful import}';

    protected $description = 'Copy integration credentials from .env into encrypted shop settings';

    public function handle(): int
    {
        $envFile = $this->option('env-file') ?: $this->resolveEnvFilePath();

        if ($envFile === null || ! is_readable($envFile)) {
            $this->error('Could not read .env file.');

            return self::FAILURE;
        }

        $keys = [
            'POSTMARK_REPLY_TO' => 'postmark_reply_to',
            'POSTMARK_REPLY_TO_NAME' => 'postmark_reply_to_name',
        ];

        $fromEnv = [];

        foreach ($keys as $envKey => $column) {
            $value = $this->readEnvValue($envFile, $envKey);

            if ($value !== null && $value !== '') {
                $fromEnv[$column] = $value;
            }
        }

        if ($fromEnv === []) {
            $this->warn('No integration credentials found in .env.');

            return self::SUCCESS;
        }

        ShopSettings::current()->persistTrusted($fromEnv);

        $credentials = ShopIntegrationCredentials::forCurrentShop();

        $this->info('Imported into shop_settings:');
        $this->line('  Messaging: '.($credentials->messagingConfigured() ? 'configured' : 'not configured'));
        $this->line('  Mail reply-to: '.(filled($credentials->mailReplyTo()) ? 'configured' : 'incomplete'));
        $this->line('  ARK Email: '.(app(\App\Ark\Mail\OutboundTransactionalMail::class)->isReady() ? 'ready' : 'not connected'));

        if ($this->option('clear-env')) {
            $this->clearEnvKeys($envFile, array_keys($keys));
            $this->info('Cleared imported keys from .env.');
        }

        return self::SUCCESS;
    }

    private function resolveEnvFilePath(): ?string
    {
        $shared = '/var/www/sites/autorepairkeeper/production/shared/.env';

        if (is_readable($shared)) {
            return $shared;
        }

        $local = base_path('.env');

        return is_readable($local) ? $local : null;
    }

    private function readEnvValue(string $envFile, string $key): ?string
    {
        $contents = file_get_contents($envFile);

        if ($contents === false) {
            return null;
        }

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);

        if ($value === '' || (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            $value = trim($value, '"\'');
        }

        return $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function clearEnvKeys(string $envFile, array $keys): void
    {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return;
        }

        $updated = array_map(function (string $line) use ($keys): string {
            foreach ($keys as $key) {
                if (str_starts_with($line, $key.'=')) {
                    return $key.'=';
                }
            }

            return $line;
        }, $lines);

        file_put_contents($envFile, implode(PHP_EOL, $updated).PHP_EOL);
    }
}
