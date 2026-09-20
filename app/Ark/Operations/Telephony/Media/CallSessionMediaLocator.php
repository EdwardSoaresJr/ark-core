<?php

namespace App\Ark\Operations\Telephony\Media;

use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Telephony\Media\Contracts\CallSessionMediaSource;

final class CallSessionMediaLocator
{
    /** @var list<CallSessionMediaSource> */
    private array $sources;

    public function __construct(
        private readonly OutboundSmsTransport $messaging,
    ) {
        $this->sources = [];
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
        return false;
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
