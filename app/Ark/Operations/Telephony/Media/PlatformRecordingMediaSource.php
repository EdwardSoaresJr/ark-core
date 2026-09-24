<?php

namespace App\Ark\Operations\Telephony\Media;

use App\Ark\Operations\Telephony\Media\Contracts\CallSessionMediaSource;
use App\Ark\Platform\Voice\PlatformRecordingClient;

final class PlatformRecordingMediaSource implements CallSessionMediaSource
{
    public function __construct(
        private readonly PlatformRecordingClient $recordings,
    ) {}

    public function scheme(): string
    {
        return 'twilio';
    }

    public function configured(): bool
    {
        return $this->recordings->ready();
    }

    /**
     * @param  list<string>  $recordingSids
     */
    public function prime(array $recordingSids): void
    {
        $this->recordings->prime($recordingSids);
    }

    public function supports(CallSessionMediaUri $uri): bool
    {
        return $uri->scheme === 'twilio' && filled($uri->twilioRecordingSid);
    }

    public function canStream(CallSessionMediaUri $uri): bool
    {
        $recordingSid = $uri->twilioRecordingSid;
        if ($recordingSid === null || $recordingSid === '') {
            return false;
        }

        return $this->recordings->owns($recordingSid);
    }

    public function fetch(CallSessionMediaUri $uri): ?CallSessionMediaPayload
    {
        $recordingSid = $uri->twilioRecordingSid;
        if ($recordingSid === null || $recordingSid === '') {
            return null;
        }

        $media = $this->recordings->fetch($recordingSid);
        if ($media === null) {
            return null;
        }

        return new CallSessionMediaPayload($media['bytes'], $media['content_type'], $uri);
    }

    public function streamPath(CallSessionMediaUri $uri): ?string
    {
        return null;
    }
}
