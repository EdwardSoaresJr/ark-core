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
            'TWILIO_ACCOUNT_SID' => 'twilio_account_sid',
            'TWILIO_AUTH_TOKEN' => 'twilio_auth_token',
            'SQUARE_APPLICATION_ID' => 'square_application_id',
            'SQUARE_ACCESS_TOKEN' => 'square_access_token',
            'SQUARE_LOCATION_ID' => 'square_location_id',
            'SQUARE_WEBHOOK_SIGNATURE_KEY' => 'square_webhook_signature_key',
            'SQUARE_ENVIRONMENT' => 'square_environment',
            'PARTSTECH_BASE_URL' => 'partstech_base_url',
            'PARTSTECH_CATALOG_PATH' => 'partstech_catalog_path',
            'PARTSTECH_USERNAME' => 'partstech_username',
            'PARTSTECH_API_KEY' => 'partstech_api_key',
            'PARTSTECH_PASSWORD' => 'partstech_password',
            'POSTMARK_TOKEN' => 'postmark_token',
            'POSTMARK_API_KEY' => 'postmark_token',
            'POSTMARK_REPLY_TO' => 'postmark_reply_to',
            'POSTMARK_REPLY_TO_NAME' => 'postmark_reply_to_name',
            'POSTMARK_MESSAGE_STREAM_ID' => 'postmark_message_stream_id',
        ];

        $fromEnv = [];

        foreach ($keys as $envKey => $column) {
            $value = $this->readEnvValue($envFile, $envKey);

            if ($value !== null && $value !== '') {
                if (isset($fromEnv[$column]) && $column === 'postmark_token') {
                    continue;
                }

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
        $this->line('  Twilio: '.($credentials->twilioConfigured() ? 'configured' : 'incomplete'));
        $this->line('  Square: '.($credentials->squareConfigured() ? 'configured' : 'incomplete'));
        $this->line('  PartsTech catalog: '.($credentials->partsTechCatalogConfigured() ? 'configured' : 'incomplete'));
        $this->line('  PartsTech quote import: '.($credentials->partsTechQuoteImportConfigured() ? 'configured' : 'incomplete'));
        $this->line('  Postmark: '.($credentials->postmarkConfigured() ? 'configured' : 'incomplete'));
        $this->line('  Twilio source: '.$credentials->twilioCredentialSource());
        $this->line('  Square source: '.$credentials->squareCredentialSource());
        $this->line('  PartsTech source: '.$credentials->partsTechCredentialSource());
        $this->line('  Postmark source: '.$credentials->postmarkCredentialSource());

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
