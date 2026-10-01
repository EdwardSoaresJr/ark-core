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

    /**
     * @return list<array{sid: string, call_sid: string, duration: int, url: string, status: string}>
     */
    public function listRecordingsSince(\DateTimeInterface $since): array
    {
        if (! $this->configured()) {
            return [];
        }

        $accountSid = (string) $this->credentials->twilioAccountSid();
        $recordings = [];
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Recordings.json";
        $query = [
            'DateCreated>' => \Illuminate\Support\Carbon::parse($since)->utc()->format('Y-m-d\TH:i:s\Z'),
            'PageSize' => 100,
        ];

        while ($url !== null && $url !== '') {
            $response = Http::withBasicAuth($accountSid, (string) $this->credentials->twilioAuthToken())
                ->get($url, $query);

            $query = [];

            if (! $response->successful()) {
                Log::warning('twilio.list_recordings_failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                break;
            }

            $rows = $response->json('recordings') ?? [];

            if (! is_array($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $sid = trim((string) ($row['sid'] ?? ''));
                $callSid = trim((string) ($row['call_sid'] ?? ''));

                if ($sid === '' || $callSid === '') {
                    continue;
                }

                $recordings[] = [
                    'sid' => $sid,
                    'call_sid' => $callSid,
                    'duration' => (int) ($row['duration'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'url' => "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Recordings/{$sid}",
                ];
            }

            $next = $response->json('next_page_uri');
            $url = is_string($next) && $next !== ''
                ? (str_starts_with($next, 'http') ? $next : 'https://api.twilio.com'.$next)
                : null;
        }

        return $recordings;
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
