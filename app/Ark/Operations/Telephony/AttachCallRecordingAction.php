<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Mobile\Push\NotifyMobileLifecyclePushAction;
use App\Ark\Operations\Telephony\Media\CallSessionMediaMetadata;

class AttachCallRecordingAction
{
    public function __construct(
        private readonly CallSessionAnalyzer $analyzer,
    ) {}

    public function findSession(?string $callSid, ?string $parentCallSid = null, ?string $dialCallSid = null): ?CallSession
    {
        foreach ([$callSid, $parentCallSid, $dialCallSid] as $sid) {
            $sid = trim((string) $sid);

            if ($sid === '') {
                continue;
            }

            $session = CallSession::query()
                ->where('provider_call_sid', $sid)
                ->first();

            if ($session !== null) {
                return $session;
            }
        }

        return null;
    }

    public function attach(
        CallSession $session,
        string $recordingUrl,
        int $duration,
        ?string $recordingSid,
        bool $voicemail = false,
    ): CallSession {
        $metadata = CallSessionMediaMetadata::forTwilioWebhook(
            $recordingUrl,
            $duration,
            $recordingSid !== '' && $recordingSid !== null ? $recordingSid : null,
        );

        if ($voicemail) {
            $session->forceFill([
                'voicemail_url' => $recordingUrl,
                'voicemail_sid' => $recordingSid !== '' && $recordingSid !== null ? $recordingSid : null,
                'voicemail_duration_seconds' => $duration > 0 ? $duration : null,
                'voicemail_capture_status' => CallSessionMediaCaptureStatus::Available,
                'voicemail_capture_error' => null,
                'voicemail_media_metadata' => $metadata,
            ])->saveQuietly();

            $this->analyzer->queueIfEligible($session->fresh());

            app(NotifyMobileLifecyclePushAction::class)->forVoicemail($session->fresh());

            return $session->fresh();
        }

        $session->forceFill([
            'recording_url' => $recordingUrl,
            'recording_sid' => $recordingSid !== '' && $recordingSid !== null ? $recordingSid : null,
            'recording_duration_seconds' => $duration > 0 ? $duration : null,
            'recording_capture_status' => CallSessionMediaCaptureStatus::Available,
            'recording_capture_error' => null,
            'recording_media_metadata' => $metadata,
        ]);

        $this->stampAnsweredFromDialRecording($session, $duration);

        $session->saveQuietly();

        $this->analyzer->queueIfEligible($session->fresh());

        return $session->fresh();
    }

    public function markFailed(CallSession $session, string $error, bool $voicemail = false): CallSession
    {
        if ($voicemail) {
            $session->forceFill([
                'voicemail_capture_status' => CallSessionMediaCaptureStatus::Failed,
                'voicemail_capture_error' => $error,
            ])->saveQuietly();

            return $session->fresh();
        }

        $session->forceFill([
            'recording_capture_status' => CallSessionMediaCaptureStatus::Failed,
            'recording_capture_error' => $error,
        ])->saveQuietly();

        return $session->fresh();
    }

    private function stampAnsweredFromDialRecording(CallSession $session, int $duration): void
    {
        if ($duration <= 0) {
            return;
        }

        if ($session->answered_at === null) {
            $session->answered_at = $session->started_at ?? now();
        }

        if (in_array($session->status, [CallSessionStatus::Missed, CallSessionStatus::Ringing], true)) {
            $session->status = CallSessionStatus::Completed;
        }
    }
}
