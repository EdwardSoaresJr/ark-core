<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ark\Operations\Printing\QzTraySigning;
use Illuminate\Console\Command;

class PrintingQzCheckCommand extends Command
{
    protected $signature = 'ark:printing:qz-check';

    protected $description = 'Fail when the QZ Tray client or signing configuration is missing';

    public function handle(): int
    {
        $clientOk = $this->clientPresent();
        $roundTrip = false;

        if (QzTraySigning::isFullyConfigured()) {
            $roundTrip = QzTraySigning::selfTestSigningRoundTrip();
        }

        $health = QzTraySigning::healthSnapshot();
        $certificate = $health['certificate'];
        $root = $health['trust_root'];

        $this->line('qz_client='.($clientOk ? 'present' : 'missing'));
        $this->line('signing_ready='.($roundTrip ? 'yes' : 'no'));
        $this->line('signing_source='.$health['source']);
        $this->line('signature_algorithm='.(string) config('printing.qz.signature_algorithm', 'sha512'));
        if ($certificate !== null) {
            $this->line('certificate='.$certificate['common_name'].' / '.$certificate['organization']);
            $this->line('certificate_sha256='.$certificate['sha256']);
            $this->line('certificate_not_after='.$certificate['not_after'].($health['certificate_expires_soon'] ? ' (expires soon)' : ''));
            $this->line('private_key_matches_certificate='.($health['private_key_matches_certificate'] ? 'yes' : 'no'));
        }
        $this->line('private_key_mode='.($health['private_key_permissions_ok'] ? 'ok' : 'readable by other accounts (chmod 600)'));
        $this->line('trust_root='.($root !== null ? $root['common_name'].' sha256='.$root['sha256'].' not_after='.$root['not_after'] : 'missing'));
        $this->line('chains_to_trust_root='.($health['chains_to_trust_root'] ? 'yes' : 'no'));
        foreach (['certificate_error_hint', 'private_key_error_hint', 'trust_root_error_hint'] as $hint) {
            if ($health[$hint] !== '') {
                $this->warn($health[$hint]);
            }
        }

        if (! $clientOk) {
            $this->error('QZ Tray client is missing from this image. Expected public/vendor/qz/qz-tray.js and public/js/ark/qz-tray.js.');

            return self::FAILURE;
        }

        if (! $roundTrip) {
            $this->error('QZ signing is not ready. Put the shop\'s pair in '.QzTraySigning::SHOP_DIRECTORY.' (or set QZ_CERTIFICATE_PATH and QZ_PRIVATE_KEY_PATH), or run ark:printing:qz-certificate --generate.');

            return self::FAILURE;
        }

        $this->info('QZ label printing check passed.');

        return self::SUCCESS;
    }

    private function clientPresent(): bool
    {
        foreach ([
            public_path('vendor/qz/qz-tray.js'),
            public_path('js/ark/qz-tray.js'),
        ] as $path) {
            if (! is_file($path) || filesize($path) < 10_000) {
                return false;
            }

            $contents = file_get_contents($path);
            if (! is_string($contents) || ! str_contains($contents, '_qz.security')) {
                return false;
            }
        }

        return true;
    }
}
