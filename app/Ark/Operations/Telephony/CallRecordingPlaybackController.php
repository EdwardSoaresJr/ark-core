<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Media\CallSessionMediaLocator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CallRecordingPlaybackController
{
    public function __construct(
        private readonly CallSessionMediaLocator $media,
    ) {}

    public function __invoke(Request $request, CallSession $callSession): Response
    {
        $kind = $request->query('kind', 'recording');
        $url = $kind === 'voicemail'
            ? $callSession->voicemail_url
            : $callSession->recording_url;

        if ($url === null || $url === '') {
            abort(404);
        }

        $path = $this->media->streamPath($url);

        if ($path !== null && is_readable($path)) {
            return response()->file($path, [
                'Content-Type' => 'audio/wav',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        $payload = $this->media->fetch($url);

        if ($payload === null) {
            abort(404, 'Recording playback is not available.');
        }

        return response($payload->bytes, 200, [
            'Content-Type' => $payload->contentType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
