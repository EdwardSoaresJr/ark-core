<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Runtime\ShopChannelScope;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Browser-local preference scope: tenant + authenticated user.
 *
 * Tenant falls back to the app host when no shop channel is bound (single-shop Core).
 */
final class UiPreferenceScope
{
    public static function tenantKey(?Request $request = null): string
    {
        if (app()->bound(ShopChannelScope::class)) {
            $id = app(ShopChannelScope::class)->id();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        $host = $request?->getHost();
        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        $configured = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($configured) && $configured !== '' ? strtolower($configured) : 'local';
    }

    public static function userKey(?User $user): string
    {
        if ($user === null) {
            return 'anon';
        }

        return (string) $user->getAuthIdentifier();
    }

    /**
     * Values exposed to layout JS (meta tags / data attributes).
     *
     * @return array{tenant: string, user: string}
     */
    public static function forRequest(Request $request): array
    {
        $user = $request->user();

        return [
            'tenant' => self::tenantKey($request),
            'user' => self::userKey($user instanceof User ? $user : null),
        ];
    }
}
