<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\Media\CallSessionMediaLocator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MobileCallRecordingPlaybackController
{
    public function __construct(
        private readonly CallSessionMediaLocator $media,
        private readonly MobileStaffAccess $access,
    ) {}

    public function __invoke(Request $request, CallSession $callSession): Response
    {
        abort_unless($this->access->canAccessShopCommunications($request->user()), 403);

        $kind = $request->query('kind', 'recording');
        $url = $kind === 'voicemail'
            ? $callSession->voicemail_url
            : $callSession->recording_url;

        if (! filled($url)) {
            abort(404, $kind === 'voicemail' ? 'No voicemail.' : 'No recording.');
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
