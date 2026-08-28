<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use Illuminate\Support\Carbon;

/**
 * Expected-reply contract stamped on outbound ConversationMessage.metadata.
 */
final class MessageActionContract
{
    public const META_ACTION = 'message_action';

    public const META_REPLIES = 'expected_replies';

    public const META_APPOINTMENT_ID = 'appointment_id';

    public const META_EXPIRES_AT = 'contract_expires_at';

    public const META_CONSUMED_AT = 'message_action_consumed_at';

    public const META_REPLY = 'message_action_reply';

    /**
     * Default appointment reminder / confirmation reply map.
     *
     * @return array<string, string>
     */
    public static function appointmentReplyMap(): array
    {
        return [
            '1' => MessageActionReply::Confirm->value,
            '2' => MessageActionReply::Reschedule->value,
            '3' => MessageActionReply::Directions->value,
            '4' => MessageActionReply::Callback->value,
        ];
    }

    /**
     * @param  array<string, string>  $expectedReplies
     * @return array<string, mixed>
     */
    public static function metadata(
        MessageActionKey $action,
        array $expectedReplies,
        ?int $appointmentId = null,
        ?Carbon $expiresAt = null,
    ): array {
        return array_filter([
            self::META_ACTION => $action->value,
            self::META_REPLIES => $expectedReplies,
            self::META_APPOINTMENT_ID => $appointmentId,
            self::META_EXPIRES_AT => $expiresAt?->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null);
    }

    public static function isOpen(ConversationMessage $message, ?Carbon $now = null): bool
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];

        if (! isset($metadata[self::META_ACTION], $metadata[self::META_REPLIES])) {
            return false;
        }

        if (! is_array($metadata[self::META_REPLIES]) || $metadata[self::META_REPLIES] === []) {
            return false;
        }

        $expires = $metadata[self::META_EXPIRES_AT] ?? null;

        if (is_string($expires) && $expires !== '') {
            try {
                if (Carbon::parse($expires)->lt($now ?? now())) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return ! self::wasConsumed($message);
    }

    public static function wasConsumed(ConversationMessage $message): bool
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $appointmentId = $metadata[self::META_APPOINTMENT_ID] ?? null;

        $query = ConversationMessage::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('direction', OperationalCommunicationDirection::Inbound)
            ->where('occurred_at', '>=', $message->occurred_at)
            ->whereNotNull('metadata->'.self::META_REPLY);

        if (is_numeric($appointmentId)) {
            $query->where('metadata->'.self::META_APPOINTMENT_ID, (int) $appointmentId);
        }

        return $query->exists();
    }

    public static function matchReply(ConversationMessage $message, string $body): ?MessageActionReply
    {
        if (! self::isOpen($message)) {
            return null;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $map = $metadata[self::META_REPLIES] ?? null;

        if (! is_array($map) || $map === []) {
            return null;
        }

        $token = self::normalizeReplyToken($body);

        if ($token === null) {
            return null;
        }

        $intent = $map[$token] ?? null;

        return is_string($intent) ? MessageActionReply::tryFrom($intent) : null;
    }

    public static function normalizeReplyToken(string $body): ?string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return null;
        }

        // Whole-body digit menus: "1", "3", "1 - Confirm", "3." etc.
        if (preg_match('/^\s*([1-9])(?:\s|[-.:)]|$)/u', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        $upper = strtoupper($trimmed);

        return match ($upper) {
            'CALL', 'CALLBACK', 'CALL ME' => '4',
            'DIRECTIONS', 'ADDRESS', 'MAPS' => '3',
            'CONFIRM', 'CONFIRMED', 'YES' => '1',
            'RESCHEDULE', 'RESCHED' => '2',
            default => null,
        };
    }
}
