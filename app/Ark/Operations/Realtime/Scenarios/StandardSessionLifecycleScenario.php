<?php

namespace App\Ark\Operations\Realtime\Scenarios;

use App\Ark\Operations\Realtime\CanonicalSessionEvent;
use App\Ark\Operations\Realtime\CanonicalSessionStream;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\TelephonyProviderType;

/**
 * Shared logical call used to prove Fake and Twilio normalize identically.
 */
final class StandardSessionLifecycleScenario
{
    public const FAKE_PROVIDER_CALL_SID = 'fake-standard-lifecycle-001';

    public const TWILIO_PROVIDER_CALL_SID = 'CA-standard-lifecycle-001';

    public const FROM_NUMBER = '7195550100';

    public const TO_NUMBER = '7195551000';

    public const FROM_USER_NAME = 'Alex Rivera';

    public const TO_USER_NAME = 'Molly Advisor';

    public static function goldenStream(): CanonicalSessionStream
    {
        return CanonicalSessionStream::fromFixture(self::fixturePath());
    }

    public static function fixturePath(): string
    {
        return dirname(__DIR__, 5).'/tests/fixtures/communications/standard-session-lifecycle.canonical.json';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function twilioRawEvents(int $fromUserId, int $toUserId): array
    {
        return [
            [
                'CallSid' => self::TWILIO_PROVIDER_CALL_SID,
                'CallStatus' => 'ringing',
                'From' => '+1'.self::FROM_NUMBER,
                'To' => '+1'.self::TO_NUMBER,
                'Direction' => 'inbound',
            ],
            [
                'CallSid' => self::TWILIO_PROVIDER_CALL_SID,
                'CallStatus' => 'in-progress',
                'From' => '+1'.self::FROM_NUMBER,
                'To' => '+1'.self::TO_NUMBER,
                'Direction' => 'inbound',
            ],
            [
                'CallSid' => self::TWILIO_PROVIDER_CALL_SID,
                'CallStatus' => 'transfer',
                'From' => '+1'.self::FROM_NUMBER,
                'To' => '+1'.self::TO_NUMBER,
                'Direction' => 'inbound',
                'from_user_id' => $fromUserId,
                'from_user_name' => self::FROM_USER_NAME,
                'to_user_id' => $toUserId,
                'to_user_name' => self::TO_USER_NAME,
            ],
            [
                'CallSid' => self::TWILIO_PROVIDER_CALL_SID,
                'CallStatus' => 'held',
                'From' => '+1'.self::FROM_NUMBER,
                'To' => '+1'.self::TO_NUMBER,
                'Direction' => 'inbound',
            ],
            [
                'CallSid' => self::TWILIO_PROVIDER_CALL_SID,
                'CallStatus' => 'completed',
                'From' => '+1'.self::FROM_NUMBER,
                'To' => '+1'.self::TO_NUMBER,
                'Direction' => 'inbound',
            ],
        ];
    }

    /**
     * @return array{
     *     provider: TelephonyProviderType,
     *     provider_call_sid: string,
     *     direction: CallSessionDirection,
     *     from_number: string,
     *     to_number: string,
     *     normalized_from: string,
     *     normalized_to: string,
     * }
     */
    public static function sessionIdentity(): array
    {
        return [
            'provider' => TelephonyProviderType::Fake,
            'provider_call_sid' => self::FAKE_PROVIDER_CALL_SID,
            'direction' => CallSessionDirection::Inbound,
            'from_number' => self::FROM_NUMBER,
            'to_number' => self::TO_NUMBER,
            'normalized_from' => self::FROM_NUMBER,
            'normalized_to' => self::TO_NUMBER,
        ];
    }

    /**
     * @return list<CanonicalSessionEvent>
     */
    public static function canonicalEvents(int $fromUserId, int $toUserId): array
    {
        return [
            new CanonicalSessionEvent(
                type: SessionEventType::SessionStarted,
                sessionIdentity: array_merge(self::sessionIdentity(), [
                    'provider_call_sid' => self::FAKE_PROVIDER_CALL_SID,
                ]),
            ),
            new CanonicalSessionEvent(type: SessionEventType::SessionAnswered),
            new CanonicalSessionEvent(
                type: SessionEventType::SessionTransferred,
                payload: [
                    'from_user_id' => $fromUserId,
                    'from_user_name' => self::FROM_USER_NAME,
                    'to_user_id' => $toUserId,
                    'to_user_name' => self::TO_USER_NAME,
                ],
            ),
            new CanonicalSessionEvent(type: SessionEventType::SessionHeld),
            new CanonicalSessionEvent(type: SessionEventType::SessionEnded),
        ];
    }
}
