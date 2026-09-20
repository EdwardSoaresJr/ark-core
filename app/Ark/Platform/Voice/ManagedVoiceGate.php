<?php

namespace App\Ark\Platform\Voice;

use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\PlatformStatusClient;

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

            return self::$memo = strtolower(trim((string) ($row['runtime_owner'] ?? ''))) === 'platform';
        }

        return self::$memo = false;
    }

    public static function resetMemo(): void
    {
        self::$memo = null;
    }
}
