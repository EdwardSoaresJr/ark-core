<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationDeviceToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Front Counter glass for /api/station/* only.
 * Staff Sanctum PATs and Dragon machine tokens cannot authenticate this surface.
 */
final class AuthenticateStationDevice
{
    public const REQUEST_ATTR = 'station_device_token';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->bearerToken($request);
        if ($plain === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = StationDeviceToken::findActiveByPlainText($plain);
        if ($token === null) {
            Log::warning('station.api.auth_failed', [
                'path' => $request->path(),
                'reason' => 'invalid_or_revoked',
            ]);

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $token->belongsToCurrentShop()) {
            Log::warning('station.api.auth_failed', [
                'path' => $request->path(),
                'reason' => 'shop_mismatch',
                'token' => $token->auditLabel(),
                'shop_identity' => config('shop.identity'),
            ]);

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token->touchLastUsed();
        $request->attributes->set(self::REQUEST_ATTR, $token);

        if (! $this->methodAllowed($request)) {
            Log::warning('station.api.rejected_method', [
                'method' => $request->getMethod(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Station method not allowed.',
            ], 405);
        }

        Log::info('station.api.access', [
            'token' => $token->auditLabel(),
            'name' => $token->name,
            'shop_identity' => $token->shop_identity,
            'path' => $request->path(),
            'method' => $request->getMethod(),
        ]);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    private function methodAllowed(Request $request): bool
    {
        if (strtoupper($request->getMethod()) === 'GET') {
            return true;
        }

        return $request->isMethod('POST') && $request->is(
            'api/station/attention-nudge',
            'api/station/dragon/chat',
            'api/station/settings',
            'api/station/tasks',
            'api/station/tasks/*',
            'api/station/calls/*/handled',
        );
    }
}
