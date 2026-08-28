<?php

namespace App\Ark\Operations\Telephony\Media\Sources;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\Media\CallSessionMediaPayload;
use App\Ark\Operations\Telephony\Media\CallSessionMediaUri;
use App\Ark\Operations\Telephony\Media\Contracts\CallSessionMediaSource;
use Illuminate\Support\Facades\Http;

final class TwilioCallSessionMediaSource implements CallSessionMediaSource
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function scheme(): string
    {
        return 'twilio';
    }

    public function supports(CallSessionMediaUri $uri): bool
    {
        return $uri->isTwilio();
    }

    public function canStream(CallSessionMediaUri $uri): bool
    {
        return $this->supports($uri) && $this->credentials->twilioConfigured();
    }

    public function fetch(CallSessionMediaUri $uri): ?CallSessionMediaPayload
    {
        if (! $this->canStream($uri)) {
            return null;
        }

        $url = str_ends_with($uri->reference, '.mp3') ? $uri->reference : $uri->reference.'.mp3';

        $response = Http::timeout(120)
            ->withBasicAuth(
                (string) $this->credentials->twilioAccountSid(),
                (string) $this->credentials->twilioAuthToken(),
            )
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '') {
            return null;
        }

        return new CallSessionMediaPayload(
            bytes: $body,
            contentType: $response->header('Content-Type') ?? 'audio/mpeg',
            uri: $uri,
        );
    }

    public function streamPath(CallSessionMediaUri $uri): ?string
    {
        return null;
    }
}
