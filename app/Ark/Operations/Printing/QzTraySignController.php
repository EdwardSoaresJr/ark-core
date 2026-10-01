<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class QzTraySignController
{
    private const MAX_SIGN_PAYLOAD_BYTES = 10000;

    public function signMessage(Request $request): JsonResponse
    {
        $data = $request->input('data');
        if (! is_string($data) || strlen($data) === 0) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        if (strlen($data) > self::MAX_SIGN_PAYLOAD_BYTES) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        if (! QzTraySigning::isFullyConfigured()) {
            return response()->json([
                'error' => 'QZ signing is not configured. Set QZ_CERTIFICATE_PATH and QZ_PRIVATE_KEY_PATH (see config/printing.php).',
            ], 501);
        }

        try {
            $signature = QzTraySigning::signBase64($data);

            Log::info('qz.sign_message_success', [
                'payload_length' => strlen($data),
            ]);

            return response()->json(['signature' => $signature]);
        } catch (Throwable $e) {
            Log::warning('qz.sign_message_failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Could not sign message for QZ Tray.'], 422);
        }
    }

    public function health(): JsonResponse
    {
        if (! QzTraySigning::isConfigured()) {
            return response()->json(['status' => 'not_configured']);
        }

        try {
            if (! QzTraySigning::selfTestSigningRoundTrip()) {
                return response()->json(['status' => 'error'], 500);
            }

            $probe = 'qz-health-probe-v1';
            $signature = QzTraySigning::signBase64($probe);

            return response()->json([
                'status' => 'ok',
                'algorithm' => (string) config('printing.qz.signature_algorithm', 'sha512'),
                'signature_length' => strlen($signature),
            ]);
        } catch (Throwable $e) {
            Log::warning('qz.sign_health_failed', ['message' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    public function printingHealth(): JsonResponse
    {
        $base = QzTraySigning::health();
        $snapshot = QzTraySigning::healthSnapshot();

        return response()->json([
            'qz_signing' => array_merge($base, [
                'certificate_path_set' => $snapshot['certificate_path_set'],
                'private_key_path_set' => $snapshot['private_key_path_set'],
                'certificate_valid' => $snapshot['certificate_valid'],
                'private_key_valid' => $snapshot['private_key_valid'],
                'certificate_error_hint' => $snapshot['certificate_error_hint'] ?? '',
                'private_key_error_hint' => $snapshot['private_key_error_hint'] ?? '',
            ]),
            'algorithm' => (string) config('printing.qz.signature_algorithm', 'sha512'),
        ]);
    }
}
