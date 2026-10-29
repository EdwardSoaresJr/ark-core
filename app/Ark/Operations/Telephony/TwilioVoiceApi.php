<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;

class TwilioVoiceApi
{
    public function configured(): bool
    {
        return ShopIntegrationCredentials::forCurrentShop()->twilioConfigured();
    }

    public function createOutboundCall(
        string $from,
        string $to,
        string $twimlUrl,
        string $statusCallbackUrl,
        ?int $timeout = null,
    ): ?string {
        if (! $this->configured() || $to === '') {
            return null;
        }

        $payload = [
            'From' => $from,
            'To' => $to,
            'Url' => $twimlUrl,
        ];
        if ($statusCallbackUrl !== '') {
            $payload['StatusCallback'] = $statusCallbackUrl;
            $payload['StatusCallbackMethod'] = 'POST';
        }
        if ($timeout !== null) {
            $payload['Timeout'] = $timeout;
        }

        $response = $this->post('Calls.json', $payload);

        $sid = $response['sid'] ?? null;

        return is_string($sid) && $sid !== '' ? $sid : null;
    }

    public function redirectCall(string $callSid, string $twimlUrl): bool
    {
        if (! $this->configured() || $callSid === '') {
            return false;
        }

        return $this->post('Calls/'.$callSid.'.json', [
            'Url' => $twimlUrl,
            'Method' => 'POST',
        ]) !== null;
    }

    /**
     * @return list<array{sid: string, call_sid: string, duration: int, url: string, status: string}>
     */
    public function listRecordingsSince(DateTimeInterface $since): array
    {
        if (! $this->configured()) {
            return [];
        }

        $credentials = ShopIntegrationCredentials::forCurrentShop();
        $sid = (string) $credentials->twilioAccountSid();
        $token = (string) $credentials->twilioAuthToken();
        $response = Http::withBasicAuth($sid, $token)
            ->timeout(20)
            ->get('https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Recordings.json', [
                'DateCreated>' => $since->format('Y-m-d'),
            ]);

        if (! $response->successful()) {
            return [];
        }

        $rows = $response->json('recordings');
        if (! is_array($rows)) {
            return [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $recordingSid = (string) ($row['sid'] ?? $row['recording_sid'] ?? '');
            if ($recordingSid === '') {
                continue;
            }
            $mapped[] = [
                'sid' => $recordingSid,
                'call_sid' => (string) ($row['call_sid'] ?? $row['callSid'] ?? ''),
                'duration' => (int) ($row['duration'] ?? 0),
                'url' => (string) ($row['url'] ?? 'https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Recordings/'.$recordingSid),
                'status' => (string) ($row['status'] ?? ''),
            ];
        }

        return $mapped;
    }

    public function hangup(string $callSid): void
    {
        if (! $this->configured() || $callSid === '') {
            return;
        }

        $this->post('Calls/'.$callSid.'.json', ['Status' => 'completed']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function post(string $path, array $payload): ?array
    {
        $credentials = ShopIntegrationCredentials::forCurrentShop();
        $sid = (string) $credentials->twilioAccountSid();
        $token = (string) $credentials->twilioAuthToken();

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->timeout(20)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/'.$path, $payload);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }
}
