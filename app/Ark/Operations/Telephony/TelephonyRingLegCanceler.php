<?php

namespace App\Ark\Operations\Telephony;

class TelephonyRingLegCanceler
{
    public function __construct(
        private readonly TelephonyRingState $ringState,
        private readonly OutboundVoiceCallControl $twilio,
    ) {}

    public function cancelCompetingLegs(string $parentCallSid, int $answeredEndpointId): void
    {
        $state = $this->ringState->get($parentCallSid);

        if ($state === null) {
            return;
        }

        foreach ($state['outbound_call_sids'] as $endpointId => $outboundCallSid) {
            if ((int) $endpointId === $answeredEndpointId) {
                continue;
            }

            $this->twilio->hangup((string) $outboundCallSid);
        }
    }

    public function cancelAllOutboundLegs(string $parentCallSid): void
    {
        $state = $this->ringState->get($parentCallSid);

        if ($state === null) {
            return;
        }

        foreach ($state['outbound_call_sids'] as $outboundCallSid) {
            $this->twilio->hangup((string) $outboundCallSid);
        }

        $this->ringState->forget($parentCallSid);
    }

    public function markAnsweredAndCancel(string $parentCallSid, int $endpointId): void
    {
        $state = $this->ringState->get($parentCallSid);

        if ($state === null || ($state['answered'] ?? false)) {
            return;
        }

        $this->ringState->markAnswered($parentCallSid, $endpointId);
        $this->cancelCompetingLegs($parentCallSid, $endpointId);

        $session = CallSession::query()
            ->where('provider_call_sid', $parentCallSid)
            ->first();

        $endpoint = TelephonyEndpoint::query()->find($endpointId);

        if ($session === null || $endpoint?->user_id === null || $session->owned_by_user_id !== null) {
            return;
        }

        $session->forceFill([
            'owned_by_user_id' => $endpoint->user_id,
            'owned_at' => now(),
            'status' => CallSessionStatus::Answered,
            'answered_at' => $session->answered_at ?? now(),
        ])->save();

        app(IncomingCallContextBroadcaster::class)->broadcastForParentCallSid($parentCallSid);
    }
}
