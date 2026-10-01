<?php

namespace App\Ark\Operations\Telephony;

/**
 * Stock Core has no voice call control implementation.
 */
final class OutboundVoiceCallControl
{
    public function configured(): bool
    {
        return false;
    }

    public function createOutboundCall(
        string $from,
        string $to,
        string $twimlUrl,
        string $statusCallbackUrl,
        ?int $timeout = null,
    ): ?string {
        return null;
    }

    public function redirectCall(string $callSid, string $twimlUrl): bool
    {
        return false;
    }

    public function hangup(string $callSid): bool
    {
        return false;
    }
}
