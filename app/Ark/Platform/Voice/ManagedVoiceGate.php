<?php

namespace App\Ark\Platform\Voice;

use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\PlatformStatusClient;

/**
 * Settings visibility for Platform-managed Voice.
 * A connected box is not enough; Voice must be an active Platform service.
 */
final class ManagedVoiceGate
{
    private static ?bool $memo = null;

    public static function platformVoiceReady(): bool
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        if (! PlatformConnection::current()->isConnected()) {
            return self::$memo = false;
        }

        $status = app(PlatformStatusClient::class)->fetch();
        if ($status === null) {
            return self::$memo = false;
        }

        foreach ($status['services'] ?? [] as $row) {
            if (! is_array($row) || ($row['key'] ?? null) !== 'voice') {
                continue;
            }

            return self::$memo = in_array((string) ($row['status'] ?? ''), ['active', 'connected'], true);
        }

        return self::$memo = false;
    }

    public static function resetMemo(): void
    {
        self::$memo = null;
    }
}
