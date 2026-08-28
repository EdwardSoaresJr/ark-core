<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use Illuminate\Support\Carbon;

/**
 * Derives CallSession current truth from a normalized SessionEvent.
 * Providers never call this — only RecordSessionEventAction does.
 */
final class ApplySessionEventToCallSessionAction
{
    public function apply(CallSession $session, SessionEventType $type, array $payload, Carbon $occurredAt): CallSession
    {
        match ($type) {
            SessionEventType::SessionStarted => $this->applyStarted($session, $occurredAt),
            SessionEventType::SessionAnswered => $this->applyAnswered($session, $occurredAt),
            SessionEventType::SessionHeld => $this->applyHeld($session),
            SessionEventType::SessionTransferred => $this->applyTransferred($session, $payload, $occurredAt),
            SessionEventType::SessionRecordingStarted,
            SessionEventType::SessionRecordingEnded => null,
            SessionEventType::SessionEnded => $this->applyEnded($session, $payload, $occurredAt),
        };

        $session->save();

        return $session->fresh();
    }

    private function applyStarted(CallSession $session, Carbon $occurredAt): void
    {
        if ($session->started_at === null) {
            $session->started_at = $occurredAt;
        }

        $session->status = CallSessionStatus::Ringing;
    }

    private function applyAnswered(CallSession $session, Carbon $occurredAt): void
    {
        $session->status = CallSessionStatus::Answered;
        $session->answered_at ??= $occurredAt;
    }

    private function applyHeld(CallSession $session): void
    {
        $session->status = CallSessionStatus::Answered;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyTransferred(CallSession $session, array $payload, Carbon $occurredAt): void
    {
        $toUserId = isset($payload['to_user_id']) ? (int) $payload['to_user_id'] : null;

        if ($toUserId !== null && $toUserId > 0) {
            $session->owned_by_user_id = $toUserId;
            $session->owned_at = $occurredAt;
        }

        $session->status = CallSessionStatus::Answered;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyEnded(CallSession $session, array $payload, Carbon $occurredAt): void
    {
        $outcome = isset($payload['outcome']) ? (string) $payload['outcome'] : null;

        $session->status = match ($outcome) {
            'failed' => CallSessionStatus::Failed,
            'missed' => CallSessionStatus::Missed,
            default => $session->answered_at !== null
                ? CallSessionStatus::Completed
                : CallSessionStatus::Missed,
        };
        $session->ended_at ??= $occurredAt;
    }
}
