<?php

namespace App\Ark\Operations\Telephony;

class TelephonyCellWhisperFlow
{
    public function whisperResponse(string $parentCallSid, int $endpointId): string
    {
        $prompt = TelephonyCallFlowSettings::fromShopSettings()->cellWhisperPrompt();

        $acceptUrl = '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'
            .'<Gather numDigits="1" action="'.htmlspecialchars($acceptUrl, ENT_XML1).'" method="POST" timeout="12">'
            .'<Say voice="alice">'.htmlspecialchars($prompt, ENT_XML1).'. Press 1 to accept.</Say>'
            .'</Gather>'
            .'<Say voice="alice">No response. Goodbye.</Say>'
            .'<Hangup/>'
            .'</Response>';
    }

    public function acceptResponse(string $parentCallSid, int $endpointId, string $digits): string
    {
        if (trim($digits) !== '1') {
            return '<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>';
        }

        $state = app(TelephonyRingState::class)->get($parentCallSid);

        if ($state !== null && ($state['answered'] ?? false) && (int) ($state['answered_endpoint_id'] ?? 0) !== $endpointId) {
            return '<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>';
        }

        if ($state !== null && ! ($state['answered'] ?? false)) {
            app(TelephonyRingLegCanceler::class)->markAnsweredAndCancel($parentCallSid, $endpointId);
            app(IncomingCallContextBroadcaster::class)->broadcastForParentCallSid($parentCallSid);
        }

        if ($state !== null && filled($state['conference_name'] ?? null)) {
            return $this->conferenceJoinResponse((string) $state['conference_name']);
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    }

    public function conferenceJoinResponse(string $conferenceName): string
    {
        throw new \RuntimeException('Voice telephony is not configured.');
    }
}
