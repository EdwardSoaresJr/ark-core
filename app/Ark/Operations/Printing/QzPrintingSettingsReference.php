<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Support\Facades\Route;

final class QzPrintingSettingsReference
{
    /**
     * @return array{
     *     health: array<string, mixed>,
     *     self_test: bool,
     *     certificate_path: string,
     *     private_key_path: string,
     *     algorithm: string,
     *     poc_url: ?string,
     *     sign_health_url: string,
     *     setup_url: string,
     * }
     */
    public static function forSettingsPage(): array
    {
        $health = QzTraySigning::healthSnapshot();
        $configured = $health['source'] === QzTraySigning::SOURCE_CONFIGURED;

        return [
            'health' => $health,
            'self_test' => QzTraySigning::selfTestSigningRoundTrip(),
            'certificate_path' => $configured ? (string) config('printing.qz.certificate_path', '') : '',
            'private_key_path' => $configured ? (string) config('printing.qz.private_key_path', '') : '',
            'algorithm' => QzTraySigning::javascriptSignatureAlgorithm(),
            'poc_url' => app()->environment('local') && Route::has('dev.qz-poc')
                ? route('dev.qz-poc')
                : null,
            'sign_health_url' => route('operations.printing.qz.sign-health'),
            'setup_url' => route('operations.printing.setup'),
        ];
    }
}
