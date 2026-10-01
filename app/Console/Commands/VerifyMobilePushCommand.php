<?php

namespace App\Console\Commands;

use App\Ark\Mobile\Push\MobilePushSettings;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Console\Command;

class VerifyMobilePushCommand extends Command
{
    protected $signature = 'ark:mobile-push:verify
                            {--migrate-legacy : Remove shop-stored Firebase JSON when the platform file is operational}
                            {--fix-env-hint : Print the correct FIREBASE_CREDENTIALS value for Coolify}';

    protected $description = 'Verify platform Firebase push transport (file path, project id, shop dispatch)';

    public function handle(): int
    {
        $settings = MobilePushSettings::current();
        $configuredPath = trim((string) config('mobile.push.credentials_path', ''));
        $issues = $this->collectIssues($settings, $configuredPath);

        foreach ($issues as $issue) {
            $this->error($issue);
        }

        if ($issues !== []) {
            if ($this->option('fix-env-hint')) {
                $this->newLine();
                $this->comment('Coolify / production .env should include:');
                $this->line('  FCM_ENABLED=true');
                $this->line('  FIREBASE_CREDENTIALS=/app/storage/app/private/firebase-mobile-service-account.json');
                $this->line('Host file (already mounted): /data/ark-shared/storage/app/private/firebase-mobile-service-account.json');
            }

            return self::FAILURE;
        }

        $this->info('Mobile push transport is operational.');
        $this->line('  project_id='.$settings->resolvedProjectId());
        $this->line('  credentials='.$settings->credentialsSourceLabel());
        $this->line('  shop_dispatch='.($settings->enabled ? 'enabled' : 'disabled'));

        if ($settings->hasStoredCredentials) {
            $this->warn('Legacy shop-stored Firebase JSON is still present (duplicate source of truth).');

            if ($this->option('migrate-legacy')) {
                ShopSettings::current()->persistTrusted([
                    'mobile_push' => [
                        'enabled' => true,
                        'firebase_project_id' => $settings->resolvedProjectId(),
                    ],
                    'mobile_push_firebase_service_account' => null,
                ]);

                $this->info('Cleared legacy shop-stored Firebase credentials.');
            } else {
                $this->comment('Run with --migrate-legacy to remove the duplicate shop JSON.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectIssues(MobilePushSettings $settings, string $configuredPath): array
    {
        $issues = [];

        if ($configuredPath === '') {
            $issues[] = 'FIREBASE_CREDENTIALS is not set in the runtime environment.';
        } elseif (str_starts_with($configuredPath, '/data/ark-shared')) {
            $issues[] = 'FIREBASE_CREDENTIALS uses a host path inside the container. Use /app/storage/app/private/firebase-mobile-service-account.json instead.';
        } elseif (! is_readable($configuredPath)) {
            $issues[] = "Firebase credentials file is not readable: {$configuredPath}";
        }

        if (! filled($settings->resolvedProjectId())) {
            $issues[] = 'Firebase project id could not be resolved from credentials or FIREBASE_PROJECT_ID.';
        }

        if (! $settings->enabled) {
            $issues[] = 'Shop mobile push dispatch is disabled (Settings → Communications → Mobile, or FCM_ENABLED=true).';
        }

        if ($settings->credentialsArray() === null) {
            $issues[] = 'Firebase service account JSON is missing (platform file and legacy shop JSON both absent).';
        }

        if ($issues === [] && ! $settings->isOperational()) {
            $issues[] = 'Mobile push is not operational for an unknown reason.';
        }

        return $issues;
    }
}
