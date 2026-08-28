<?php

namespace App\Ark\Operations\Telephony\Media;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\Media\Contracts\CallSessionMediaSource;
use App\Ark\Operations\Telephony\Media\Sources\TwilioCallSessionMediaSource;

final class CallSessionMediaLocator
{
    /** @var list<CallSessionMediaSource> */
    private array $sources;

    public function __construct(
        TwilioCallSessionMediaSource $twilio,
        private readonly ShopIntegrationCredentials $credentials,
    ) {
        $this->sources = [$twilio];
    }

    public function parse(?string $reference): ?CallSessionMediaUri
    {
        return CallSessionMediaUri::parse($reference);
    }

    public function canStream(?string $reference): bool
    {
        $uri = $this->parse($reference);

        if ($uri === null) {
            return false;
        }

        return $this->sourceFor($uri)?->canStream($uri) ?? false;
    }

    public function fetch(?string $reference): ?CallSessionMediaPayload
    {
        $uri = $this->parse($reference);

        if ($uri === null) {
            return null;
        }

        return $this->sourceFor($uri)?->fetch($uri);
    }

    public function streamPath(?string $reference): ?string
    {
        $uri = $this->parse($reference);

        if ($uri === null) {
            return null;
        }

        return $this->sourceFor($uri)?->streamPath($uri);
    }

    public function playbackAvailable(): bool
    {
        return $this->credentials->twilioConfigured();
    }

    private function sourceFor(CallSessionMediaUri $uri): ?CallSessionMediaSource
    {
        foreach ($this->sources as $source) {
            if ($source->supports($uri)) {
                return $source;
            }
        }

        return null;
    }
}
