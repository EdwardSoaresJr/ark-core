<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Media\CallSessionMediaLocator;

final class CallSessionAudioFetcher
{
    public function __construct(
        private readonly CallSessionMediaLocator $media,
    ) {}

    public function fetchMp3(CallSession $callSession, string $kind = 'recording'): ?string
    {
        $url = $kind === 'voicemail'
            ? $callSession->voicemail_url
            : $callSession->recording_url;

        if (! filled($url)) {
            return null;
        }

        $payload = $this->media->fetch($url);

        return $payload?->bytes;
    }
}
