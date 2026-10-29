<?php

namespace App\Ark\Operations\Telephony;

final class OutboundVoiceCallControl
{
    public function __construct(
        private readonly TwilioVoiceApi $twilio,
    ) {}

    public function configured(): bool
    {
        return $this->twilio->configured();
    }

    public function createOutboundCall(
        string $from,
        string $to,
        string $twimlUrl,
        string $statusCallbackUrl,
        ?int $timeout = null,
    ): ?string {
        return $this->twilio->createOutboundCall($from, $to, $twimlUrl, $statusCallbackUrl, $timeout);
    }

    public function redirectCall(string $callSid, string $twimlUrl): bool
    {
        return $this->twilio->redirectCall($callSid, $twimlUrl);
    }

    public function hangup(string $callSid): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $this->twilio->hangup($callSid);

        return true;
    }
}
