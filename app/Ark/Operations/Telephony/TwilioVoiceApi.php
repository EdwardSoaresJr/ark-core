<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioVoiceApi
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function configured(): bool
    {
        return $this->credentials->twilioConfigured();
    }

    public function createOutboundCall(
        string $from,
        string $to,
        string $twimlUrl,
        string $statusCallbackUrl,
        ?int $timeout = null,
    ): ?string {
        if (! $this->configured()) {
            return null;
        }

        $accountSid = (string) $this->credentials->twilioAccountSid();

        $payload = [
            'From' => $from,
            'To' => $to,
            'Url' => $twimlUrl,
            'Method' => 'POST',
            'StatusCallback' => $statusCallbackUrl,
            'StatusCallbackEvent' => 'initiated ringing answered completed',
            'StatusCallbackMethod' => 'POST',
        ];

        if ($timeout !== null && $timeout > 0) {
            $payload['Timeout'] = $timeout;
        }

        $response = Http::withBasicAuth($accountSid, (string) $this->credentials->twilioAuthToken())
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json", $payload);

        if (! $response->successful()) {
            Log::warning('twilio.outbound_call_failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $callSid = trim((string) ($response->json('sid') ?? ''));

        return $callSid !== '' ? $callSid : null;
    }

    public function redirectCall(string $callSid, string $twimlUrl): bool
    {
        if (! $this->configured() || $callSid === '' || $twimlUrl === '') {
            return false;
        }

        $accountSid = (string) $this->credentials->twilioAccountSid();

        $response = Http::withBasicAuth($accountSid, (string) $this->credentials->twilioAuthToken())
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$callSid}.json", [
                'Url' => $twimlUrl,
                'Method' => 'POST',
            ]);

        if (! $response->successful()) {
            Log::warning('twilio.redirect_call_failed', [
                'call_sid' => $callSid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function hangup(string $callSid): void
    {
        if (! $this->configured() || $callSid === '') {
            return;
        }

        $accountSid = (string) $this->credentials->twilioAccountSid();

        Http::withBasicAuth($accountSid, (string) $this->credentials->twilioAuthToken())
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$callSid}.json", [
                'Status' => 'completed',
            ]);
    }
}
