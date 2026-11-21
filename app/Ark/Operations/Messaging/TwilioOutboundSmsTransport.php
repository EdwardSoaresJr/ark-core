<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TwilioOutboundSmsTransport implements OutboundSmsTransport
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function isConfigured(): bool
    {
        return $this->credentials->twilioConfigured() && filled($this->credentials->twilioFromNumber());
    }

    public function send(string $toPhone, string $body, array $mediaUrls = [], array $context = []): OutboundSmsResult
    {
        $sid = (string) $this->credentials->twilioAccountSid();
        $token = (string) $this->credentials->twilioAuthToken();
        $from = (string) $this->credentials->twilioFromNumber();

        $payload = [
            'To' => $toPhone,
            'From' => $from,
            'Body' => $body,
        ];
        if ($mediaUrls !== []) {
            $payload['MediaUrl'] = $mediaUrls;
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->timeout(20)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Messages.json', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio rejected the outbound message.');
        }

        return new OutboundSmsResult(
            (string) $response->json('sid'),
            (string) ($response->json('status') ?: 'queued'),
        );
    }
}
