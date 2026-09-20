<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PortalObservationSession
{
    public const SESSION_KEY = 'portal_observation_session_id';

    public static function start(Request $request): string
    {
        $sessionId = (string) Str::uuid();
        $request->session()->put(self::SESSION_KEY, $sessionId);

        return $sessionId;
    }

    public static function id(Request $request): ?string
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }
}
