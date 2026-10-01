<?php

namespace App\Ark\Platform\Http;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class VerifyPlatformFabricSignature
{
    public const PATH = '/webhooks/cloud/fabric/events';

    private const MAX_SKEW_SECONDS = 300;

    private const NONCE_TTL_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $platform = PlatformConnection::current();
        if (! $platform->isConnected() || ! filled($platform->credential())) {
            abort(401, 'Platform is not connected.');
        }

        $installationId = (string) $request->header('X-Ark-Installation-Id', '');
        $expectedInstallation = InstallationIdentity::uuid();
        if ($installationId === '' || ! hash_equals($expectedInstallation, $installationId)) {
            abort(401, 'Installation mismatch.');
        }

        $timestamp = (string) $request->header('X-Ark-Timestamp', '');
        $nonce = (string) $request->header('X-Ark-Nonce', '');
        $signature = (string) $request->header('X-Ark-Signature', '');

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            abort(401, 'Missing signature headers.');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            abort(401, 'Timestamp rejected.');
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($request->getMethod()),
            '/'.ltrim($request->path(), '/'),
            hash('sha256', $rawBody),
        ]), (string) $platform->credential());

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature.');
        }

        $nonceKey = 'fabric-nonce:'.$installationId.':'.$nonce;
        if (! Cache::add($nonceKey, true, self::NONCE_TTL_SECONDS)) {
            abort(401, 'Replay rejected.');
        }

        return $next($request);
    }
}
