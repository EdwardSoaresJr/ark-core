<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TwilioPhoneLookupClient
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @return array{
     *     valid: bool,
     *     phone_number: ?string,
     *     line_type: ?string,
     *     carrier_name: ?string,
     *     validation_errors: list<string>,
     *     raw: array<string, mixed>
     * }
     */
    public function lookupLineType(string $phone): array
    {
        $accountSid = $this->credentials->twilioAccountSid();
        $authToken = $this->credentials->twilioAuthToken();
        $e164 = PhoneNumber::toE164($phone);

        if (! filled($accountSid) || ! filled($authToken)) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        if ($e164 === null) {
            throw new RuntimeException('Phone number is invalid.');
        }

        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->acceptJson()
                ->timeout(15)
                ->get('https://lookups.twilio.com/v2/PhoneNumbers/'.rawurlencode($e164), [
                    'Fields' => 'line_type_intelligence',
                ]);
        } catch (Throwable $exception) {
            Log::warning('twilio_phone_lookup_request_failed', [
                'phone' => $e164,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Twilio Lookup request failed: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            $detail = (string) ($response->json('message') ?? $response->body());

            Log::warning('twilio_phone_lookup_rejected', [
                'phone' => $e164,
                'status' => $response->status(),
                'detail' => $detail,
            ]);

            throw new RuntimeException('Twilio Lookup rejected the number: '.$detail);
        }

        $data = $response->json();
        if (! is_array($data)) {
            $data = [];
        }

        $lineType = data_get($data, 'line_type_intelligence.type');
        $carrier = data_get($data, 'line_type_intelligence.carrier_name');
        $validationErrors = data_get($data, 'validation_errors', []);

        return [
            'valid' => filter_var($data['valid'] ?? false, FILTER_VALIDATE_BOOL),
            'phone_number' => isset($data['phone_number']) ? (string) $data['phone_number'] : null,
            'line_type' => is_string($lineType) && $lineType !== '' ? $lineType : null,
            'carrier_name' => is_string($carrier) && $carrier !== '' ? $carrier : null,
            'validation_errors' => is_array($validationErrors)
                ? array_values(array_map(static fn (mixed $error): string => (string) $error, $validationErrors))
                : [],
            'raw' => $data,
        ];
    }
}
