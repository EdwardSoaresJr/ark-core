<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

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
     * }
     */
    public static function forSettingsPage(): array
    {
        return [
            'health' => QzTraySigning::healthSnapshot(),
            'self_test' => QzTraySigning::selfTestSigningRoundTrip(),
            'certificate_path' => (string) config('printing.qz.certificate_path', ''),
            'private_key_path' => (string) config('printing.qz.private_key_path', ''),
            'algorithm' => QzTraySigning::javascriptSignatureAlgorithm(),
            'poc_url' => app()->environment('local') && \Illuminate\Support\Facades\Route::has('dev.qz-poc')
                ? route('dev.qz-poc')
                : null,
            'sign_health_url' => route('operations.printing.qz.sign-health'),
        ];
    }
}
