<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionMediaCaptureStatus;

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

        $recordingPath = $this->pathFor($callSession, 'recording');
        $voicemailPath = $this->pathFor($callSession, 'voicemail');

        if ($recordingPath !== null && $voicemailPath !== null
            && $webProjection['recording_url'] === null
            && $this->sameArtifact($callSession)) {
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

        if ($callSession->mediaCaptureStatus($kind) === CallSessionMediaCaptureStatus::Failed) {
            return null;
        }

        return '/calls/'.$callSession->id.'/recording?kind='.$kind;
    }

    private function sameArtifact(CallSession $callSession): bool
    {
        if (filled($callSession->recording_sid) && filled($callSession->voicemail_sid)) {
            return $callSession->recording_sid === $callSession->voicemail_sid;
        }

        return filled($callSession->recording_url)
            && filled($callSession->voicemail_url)
            && $callSession->recording_url === $callSession->voicemail_url;
    }
}
