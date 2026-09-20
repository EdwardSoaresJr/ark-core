<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Media\CallSessionMediaLocator;
use App\Ark\Operations\Telephony\Media\CallSessionMediaUri;
use Illuminate\Support\Facades\Route;

final class CallRecordingPlayback
{
    public function __construct(
        private readonly CallSessionMediaLocator $media,
    ) {}

    public function available(): bool
    {
        return $this->media->playbackAvailable();
    }

    public function canPlay(CallSession $callSession, string $kind = 'recording'): bool
    {
        $sourceUrl = $kind === 'voicemail'
            ? $callSession->voicemail_url
            : $callSession->recording_url;

        if (! filled($sourceUrl)) {
            return false;
        }

        if ($callSession->mediaCaptureStatus($kind) === CallSessionMediaCaptureStatus::Failed) {
            return false;
        }

        return $this->media->canStream($sourceUrl);
    }

    public function urlFor(CallSession $callSession, string $kind = 'recording'): ?string
    {
        if (! $this->canPlay($callSession, $kind)) {
            return null;
        }

        if (! Route::has('operations.telephony.call-sessions.recording')) {
            return null;
        }

        return route('operations.telephony.call-sessions.recording', [
            'callSession' => $callSession,
            'kind' => $kind,
        ]);
    }

    /**
     * @return array{
     *     has_recording: bool,
     *     has_voicemail: bool,
     *     recording_url: ?string,
     *     voicemail_url: ?string,
     *     recording_capture_status: ?string,
     *     recording_capture_label: ?string,
     *     voicemail_capture_status: ?string,
     *     voicemail_capture_label: ?string,
     *     show_play_recording_action: bool,
     *     show_play_voicemail_action: bool,
     * }
     */
    public function projectFor(CallSession $callSession): array
    {
        $recordingUrl = $this->urlFor($callSession, 'recording');
        $voicemailUrl = $this->urlFor($callSession, 'voicemail');

        if ($voicemailUrl !== null && $this->isSameArtifact($callSession)) {
            $recordingUrl = null;
        }

        return [
            'has_recording' => $recordingUrl !== null,
            'has_voicemail' => $voicemailUrl !== null,
            'recording_url' => $recordingUrl,
            'voicemail_url' => $voicemailUrl,
            'recording_capture_status' => $callSession->recording_capture_status?->value,
            'recording_capture_label' => $callSession->recording_capture_status?->operationalLabel('Recording'),
            'voicemail_capture_status' => $callSession->voicemail_capture_status?->value,
            'voicemail_capture_label' => $callSession->voicemail_capture_status?->operationalLabel('Voicemail'),
            'show_play_recording_action' => $recordingUrl !== null,
            'show_play_voicemail_action' => $voicemailUrl !== null,
        ];
    }

    private function isSameArtifact(CallSession $callSession): bool
    {
        if (
            filled($callSession->recording_sid)
            && filled($callSession->voicemail_sid)
        ) {
            return $callSession->recording_sid === $callSession->voicemail_sid;
        }

        if (! filled($callSession->recording_url) || ! filled($callSession->voicemail_url)) {
            return false;
        }

        if ($callSession->recording_url === $callSession->voicemail_url) {
            return true;
        }

        $recording = CallSessionMediaUri::parse($callSession->recording_url);
        $voicemail = CallSessionMediaUri::parse($callSession->voicemail_url);

        if ($recording?->twilioRecordingSid !== null && $recording->twilioRecordingSid === $voicemail?->twilioRecordingSid) {
            return true;
        }

        return false;
    }
}
