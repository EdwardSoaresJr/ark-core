<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;

/**
 * Mobile playback URLs for call recordings — Sanctum-authenticated proxy routes.
 */
final class MobileCallRecordingPlayback
{
    public function __construct(
        private readonly CallRecordingPlayback $playback,
    ) {}

    /**
     * @return array{
     *     has_recording: bool,
     *     has_voicemail: bool,
     *     recording_path: ?string,
     *     voicemail_path: ?string,
     * }
     */
    public function projectFor(CallSession $callSession): array
    {
        $webProjection = $this->playback->projectFor($callSession);

        $recordingPath = $webProjection['has_recording']
            ? $this->pathFor($callSession, 'recording')
            : null;

        $voicemailPath = $webProjection['has_voicemail']
            ? $this->pathFor($callSession, 'voicemail')
            : null;

        if ($recordingPath === null && $voicemailPath !== null) {
            // Same Twilio artifact — prefer voicemail path when recording is deduped.
        } elseif ($recordingPath !== null && $voicemailPath !== null
            && $webProjection['recording_url'] === null) {
            $recordingPath = null;
        }

        return [
            'has_recording' => $recordingPath !== null,
            'has_voicemail' => $voicemailPath !== null,
            'recording_path' => $recordingPath,
            'voicemail_path' => $voicemailPath,
        ];
    }

    private function pathFor(CallSession $callSession, string $kind): ?string
    {
        $sourceUrl = $kind === 'voicemail'
            ? $callSession->voicemail_url
            : $callSession->recording_url;

        if (! filled($sourceUrl)) {
            return null;
        }

        return '/calls/'.$callSession->id.'/recording?kind='.$kind;
    }
}
