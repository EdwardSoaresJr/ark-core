<?php

namespace App\Ark\Operations\Telephony;

use DateTimeInterface;

/**
 * Call-leg helper kept for existing shop call records.
 * Carrier requests are not made from public Core.
 */
class TwilioVoiceApi
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

    /**
     * @return list<array{sid: string, call_sid: string, duration: int, url: string, status: string}>
     */
    public function listRecordingsSince(DateTimeInterface $since): array
    {
        return [];
    }

    public function hangup(string $callSid): void {}
}
