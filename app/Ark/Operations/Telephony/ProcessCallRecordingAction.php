<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Mobile\Push\NotifyMobileLifecyclePushAction;
use App\Ark\Operations\Telephony\Media\CallSessionMediaMetadata;
use Illuminate\Http\Request;

class ProcessCallRecordingAction
{
    public function __construct(
        private readonly CallSessionAnalyzer $analyzer,
    ) {}

    public function execute(Request $request, bool $voicemail = false): ?CallSession
    {
        $callSid = trim((string) $request->input('CallSid', ''));
        $recordingSid = trim((string) $request->input('RecordingSid', ''));
        $recordingUrl = trim((string) $request->input('RecordingUrl', ''));
        $duration = (int) $request->input('RecordingDuration', 0);

        if ($callSid === '' || $recordingUrl === '') {
            return null;
        }

        $session = CallSession::query()
            ->where('provider_call_sid', $callSid)
            ->first();

        if ($session === null) {
            return null;
        }

        $metadata = CallSessionMediaMetadata::forTwilioWebhook($recordingUrl, $duration, $recordingSid !== '' ? $recordingSid : null);

        if ($voicemail) {
            $session->forceFill([
                'voicemail_url' => $recordingUrl,
                'voicemail_sid' => $recordingSid !== '' ? $recordingSid : null,
                'voicemail_duration_seconds' => $duration > 0 ? $duration : null,
                'voicemail_capture_status' => CallSessionMediaCaptureStatus::Available,
                'voicemail_capture_error' => null,
                'voicemail_media_metadata' => $metadata,
            ])->saveQuietly();

            $this->analyzer->queueIfEligible($session->fresh());

            app(NotifyMobileLifecyclePushAction::class)->forVoicemail($session->fresh());

            return $session;
        }

        $session->forceFill([
            'recording_url' => $recordingUrl,
            'recording_sid' => $recordingSid !== '' ? $recordingSid : null,
            'recording_duration_seconds' => $duration > 0 ? $duration : null,
            'recording_capture_status' => CallSessionMediaCaptureStatus::Available,
            'recording_capture_error' => null,
            'recording_media_metadata' => $metadata,
        ])->saveQuietly();

        $this->analyzer->queueIfEligible($session->fresh());

        return $session;
    }
}
