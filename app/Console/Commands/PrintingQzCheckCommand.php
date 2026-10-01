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

        $this->line('qz_client='.($clientOk ? 'present' : 'missing'));
        $this->line('signing_ready='.($roundTrip ? 'yes' : 'no'));
        $this->line('signature_algorithm='.(string) config('printing.qz.signature_algorithm', 'sha512'));

        if (! $clientOk) {
            $this->error('QZ Tray client is missing from this image. Expected public/vendor/qz/qz-tray.js and public/js/ark/qz-tray.js.');

            return self::FAILURE;
        }

        if (! $roundTrip) {
            $this->error('QZ signing is not ready. Set QZ_CERTIFICATE_PATH and QZ_PRIVATE_KEY_PATH on persistent storage.');

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
