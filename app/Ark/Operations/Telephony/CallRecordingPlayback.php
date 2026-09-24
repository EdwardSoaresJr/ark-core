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
     * @param  iterable<CallSession>  $sessions
     */
    public function prime(iterable $sessions): void
    {
        $sids = [];
        foreach ($sessions as $session) {
            foreach ($this->recordingSids($session) as $sid) {
                $sids[$sid] = $sid;
            }
        }

        $this->media->primeRecordingSids(array_values($sids));
    }

    /**
     * @return list<string>
     */
    public function recordingSids(CallSession $callSession): array
    {
        $sids = [];
        foreach ([$callSession->recording_url, $callSession->voicemail_url] as $url) {
            $sid = CallSessionMediaUri::parse(is_string($url) ? $url : null)?->twilioRecordingSid;
            if (! is_string($sid) || preg_match('/^RE[a-fA-F0-9]{32}$/', $sid) !== 1) {
                continue;
            }
            $sids[$sid] = $sid;
        }

        return array_values($sids);
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
     *     recording_state: string,
     *     voicemail_state: string,
     * }
     */
    public function projectFor(CallSession $callSession): array
    {
        $this->prime([$callSession]);

        $recordingUrl = $this->urlFor($callSession, 'recording');
        $voicemailUrl = $this->urlFor($callSession, 'voicemail');
        $recordingStored = filled($callSession->recording_url) || filled($callSession->recording_sid);
        $voicemailStored = filled($callSession->voicemail_url) || filled($callSession->voicemail_sid);

        if ($voicemailUrl !== null && $this->isSameArtifact($callSession)) {
            $recordingUrl = null;
            $recordingStored = false;
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
            'recording_state' => $this->mediaState($recordingUrl, $recordingStored, $callSession->recording_capture_status),
            'voicemail_state' => $this->mediaState($voicemailUrl, $voicemailStored, $callSession->voicemail_capture_status),
        ];
    }

    private function mediaState(?string $playbackUrl, bool $stored, ?CallSessionMediaCaptureStatus $capture): string
    {
        if ($playbackUrl !== null) {
            return 'playable';
        }

        if ($capture === CallSessionMediaCaptureStatus::Failed || $stored) {
            return 'unavailable';
        }

        return 'none';
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
