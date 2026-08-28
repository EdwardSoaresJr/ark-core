<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioMessagingSender
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @param  list<string>  $mediaUrls
     */
    public function send(string $toPhone, string $body, array $mediaUrls = []): TwilioOutboundResult
    {
        $settings = ShopSettings::current();
        $accountSid = $this->credentials->twilioAccountSid();
        $authToken = $this->credentials->twilioAuthToken();
        $from = PhoneNumber::toE164($settings->telephony_inbound_number);
        $to = PhoneNumber::toE164($toPhone);

        if (! filled($accountSid) || ! filled($authToken)) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        if ($from === null) {
            throw new RuntimeException('Shop SMS number is not configured.');
        }

        if ($to === null) {
            throw new RuntimeException('Customer phone number is invalid.');
        }

        $payload = [
            'From' => $from,
            'To' => $to,
            'Body' => $body,
            'StatusCallback' => route('webhooks.communications.twilio.messaging.status'),
            'StatusCallbackMethod' => 'POST',
        ];

        if ($mediaUrls !== []) {
            $payload['MediaUrl'] = count($mediaUrls) === 1
                ? $mediaUrls[0]
                : array_values($mediaUrls);
        }

        /** @var Response $response */
        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->timeout(20)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

        if (! $response->successful()) {
            $detail = (string) ($response->json('message') ?? $response->body());

            throw new RuntimeException('Twilio rejected the outbound message: '.$detail);
        }

        $data = $response->json();

        return new TwilioOutboundResult(
            messageSid: (string) ($data['sid'] ?? ''),
            status: (string) ($data['status'] ?? ''),
        );
    }
}
