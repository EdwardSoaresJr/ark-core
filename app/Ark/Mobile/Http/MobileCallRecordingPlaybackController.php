<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

final class MobileCallRecordingPlaybackController
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
        private readonly CallRecordingPlayback $playback,
        private readonly MobileStaffAccess $access,
    ) {}

    public function __invoke(Request $request, CallSession $callSession): Response
    {
        abort_unless($this->access->canAccessShopCommunications($request->user()), 403);

        if (! $this->playback->available()) {
            abort(404, 'Recording playback is not available.');
        }

        $kind = $request->query('kind', 'recording');
        $url = $kind === 'voicemail'
            ? $callSession->voicemail_url
            : $callSession->recording_url;

        if ($url === null || $url === '') {
            abort(404);
        }

        $accountSid = null;
        $authToken = null;

        if (! filled($accountSid) || ! filled($authToken)) {
            abort(404, 'Recording playback is not available.');
        }

        $playbackUrl = str_ends_with($url, '.mp3') ? $url : $url.'.mp3';

        $response = Http::withBasicAuth($accountSid, $authToken)->get($playbackUrl);

        if (! $response->successful()) {
            abort(404);
        }

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type') ?? 'audio/mpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
