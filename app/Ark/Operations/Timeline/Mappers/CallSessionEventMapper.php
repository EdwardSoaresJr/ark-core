<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;

final class CallSessionEventMapper
{
    public function __construct(
        private readonly CallRecordingPlayback $recordingPlayback,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
    ) {}

    public function map(CallSession $session): OperationalEventEntry
    {
        $session->loadMissing(['owner:id,name', 'customer:id,first_name,last_name', 'repairOrder']);

        $tone = $session->direction === CallSessionDirection::Inbound
            ? OperationalEventTone::Customer
            : OperationalEventTone::Shop;

        $isMissed = $session->status === CallSessionStatus::Missed;
        $hasVoicemail = filled($session->voicemail_url);
        $playback = $this->recordingPlayback->projectFor($session);
        $displayPhone = $this->callerDisplayPhone->forSession($session);

        $kind = match (true) {
            $hasVoicemail => OperationalEventKind::Voicemail,
            $isMissed => OperationalEventKind::MissedCall,
            default => OperationalEventKind::Call,
        };

        $headline = match (true) {
            $hasVoicemail => 'Voicemail',
            $isMissed => $session->direction->queueLabel().' call · missed',
            default => $session->direction->queueLabel().' call · '.$session->status->operationalLabel(),
        };

        $body = collect([
            $displayPhone,
            $session->talkDurationLabel(),
            $session->status->operationalLabel(),
            filled($session->owned_by_user_id) ? $session->owner?->name : null,
            $session->repairOrder ? 'RO #'.$session->repairOrder->repair_order_id : null,
        ])->filter()->join(' · ');

        return new OperationalEventEntry(
            source: OperationalEventSource::CallSession,
            kind: $kind,
            occurredAt: $session->started_at ?? $session->created_at ?? now(),
            headline: $headline,
            body: $body !== '' ? $body : null,
            actor: $session->owner?->name,
            tone: $tone,
            links: [],
            metadata: array_merge($playback, [
                'hub_filter' => 'call',
                'timeline_category' => $hasVoicemail ? 'voicemail' : ($isMissed ? 'missed_call' : 'call'),
                'call_session_id' => $session->id,
                'status_label' => $session->status->operationalLabel(),
                'channel_label' => 'Phone',
                'display_phone' => $displayPhone,
                'direction_label' => $session->direction->queueLabel(),
                'duration_label' => $session->talkDurationLabel(),
                'is_missed' => $isMissed,
                'has_voicemail' => $hasVoicemail,
                'has_recording' => filled($session->recording_url),
                'analysis_summary' => $session->analysisSummary(),
                'voicemail_duration_seconds' => $session->voicemail_duration_seconds,
                'recording_duration_seconds' => $session->recording_duration_seconds,
            ]),
            subject: $session,
        );
    }
}
