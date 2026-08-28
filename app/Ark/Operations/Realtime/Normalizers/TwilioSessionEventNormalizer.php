<?php

namespace App\Ark\Operations\Realtime\Normalizers;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Realtime\CanonicalSessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyProviderType;

/**
 * Pure translation: Twilio transport signals → canonical SessionEvent DTO.
 */
final class TwilioSessionEventNormalizer
{
    public function fromPayload(IncomingCallPayload $payload): ?CanonicalSessionEvent
    {
        $raw = array_merge($payload->rawPayload, [
            'CallSid' => $payload->providerCallSid,
            'From' => $payload->fromNumber,
            'To' => $payload->toNumber,
            'Direction' => $payload->direction === CallSessionDirection::Outbound ? 'outbound' : 'inbound',
        ]);

        if (! isset($raw['CallStatus'])) {
            $raw['CallStatus'] = $this->statusFromCallSessionStatus($payload->status);
        }

        return $this->fromRaw($raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function fromRaw(array $raw): ?CanonicalSessionEvent
    {
        $status = strtolower(trim((string) ($raw['CallStatus'] ?? '')));

        return match ($status) {
            'ringing', 'initiated' => $this->started($raw),
            'in-progress', 'answered' => new CanonicalSessionEvent(type: SessionEventType::SessionAnswered),
            'transfer' => $this->transferred($raw),
            'held' => new CanonicalSessionEvent(type: SessionEventType::SessionHeld),
            'completed' => new CanonicalSessionEvent(type: SessionEventType::SessionEnded),
            'failed' => new CanonicalSessionEvent(
                type: SessionEventType::SessionEnded,
                payload: ['outcome' => 'failed'],
            ),
            'no-answer', 'busy', 'canceled' => new CanonicalSessionEvent(
                type: SessionEventType::SessionEnded,
                payload: ['outcome' => 'missed'],
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function started(array $raw): CanonicalSessionEvent
    {
        $from = trim((string) ($raw['From'] ?? ''));
        $to = trim((string) ($raw['To'] ?? ''));
        $normalizedFrom = PhoneNumber::normalize($from) ?? PhoneNumber::digits($from);
        $normalizedTo = PhoneNumber::normalize($to);
        $direction = strtolower(trim((string) ($raw['Direction'] ?? 'inbound')));

        return new CanonicalSessionEvent(
            type: SessionEventType::SessionStarted,
            sessionIdentity: [
                'provider' => TelephonyProviderType::Twilio,
                'provider_call_sid' => trim((string) ($raw['CallSid'] ?? '')),
                'direction' => $direction === 'outbound'
                    ? CallSessionDirection::Outbound
                    : CallSessionDirection::Inbound,
                'from_number' => $from !== '' ? $from : $normalizedFrom,
                'to_number' => $to,
                'normalized_from' => $normalizedFrom,
                'normalized_to' => $normalizedTo,
                'raw_payload' => $raw,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function transferred(array $raw): CanonicalSessionEvent
    {
        return new CanonicalSessionEvent(
            type: SessionEventType::SessionTransferred,
            payload: array_filter([
                'from_user_id' => isset($raw['from_user_id']) ? (int) $raw['from_user_id'] : null,
                'from_user_name' => isset($raw['from_user_name']) ? (string) $raw['from_user_name'] : null,
                'to_user_id' => isset($raw['to_user_id']) ? (int) $raw['to_user_id'] : null,
                'to_user_name' => isset($raw['to_user_name']) ? (string) $raw['to_user_name'] : null,
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    private function statusFromCallSessionStatus(\App\Ark\Operations\Telephony\CallSessionStatus $status): string
    {
        return match ($status) {
            \App\Ark\Operations\Telephony\CallSessionStatus::Ringing => 'ringing',
            \App\Ark\Operations\Telephony\CallSessionStatus::Answered => 'in-progress',
            \App\Ark\Operations\Telephony\CallSessionStatus::Completed => 'completed',
            \App\Ark\Operations\Telephony\CallSessionStatus::Missed => 'no-answer',
            \App\Ark\Operations\Telephony\CallSessionStatus::Failed => 'failed',
        };
    }
}
