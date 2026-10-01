<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Facades\Route;

/**
 * Realtime inbound SMS popup when Core has no ConversationMessage.
 *
 * Core unread polling cannot recover these interrupts. Smallest later restore:
 * cache the last hosted SMS interrupt the same way portal and website-lead
 * popups are cached, then include it in CommsInterruptResolver until dismissed.
 */
final class HostedSmsInterrupt
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function fromPlatformInbound(
        array $payload,
        string $fromPhone,
        string $body,
        bool $hasMedia,
        ?int $conversationId,
        ?int $customerId,
        ?string $customerName,
    ): ?array {
        $platformMessageId = self::publicId($payload['message_public_id'] ?? null);

        if ($platformMessageId === null) {
            return null;
        }

        $platformConversationId = self::publicId($payload['conversation_public_id'] ?? null);

        if ($conversationId === null && $platformConversationId === null) {
            return null;
        }

        $kind = $hasMedia ? 'mms' : 'sms';
        $matched = $customerId !== null;
        $headline = $matched ? trim((string) $customerName) : 'Unknown';
        $displayPhone = PhoneNumber::display($fromPhone) ?? $fromPhone;
        $customerUrl = $matched && Route::has('operations.customers.show')
            ? route('operations.customers.show', $customerId)
            : null;
        $replyUrl = self::replyUrl($conversationId, $customerUrl, $platformConversationId);

        if (! filled($replyUrl)) {
            return null;
        }

        $markReadUrl = $conversationId !== null && Route::has('operations.conversations.read')
            ? route('operations.conversations.read', $conversationId)
            : null;

        $interrupt = [
            'kind' => $kind,
            'channel' => $kind,
            'channel_label' => $hasMedia ? 'MMS' : 'SMS',
            'direction' => 'inbound',
            'state' => 'unread',
            'state_label' => $hasMedia ? 'MMS' : 'SMS',
            'snippet' => self::snippet($body),
            'display_phone' => $displayPhone,
            'headline' => $headline !== '' ? $headline : 'Unknown',
            'matched' => $matched,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_url' => $customerUrl,
            'reply_url' => $replyUrl,
            'show_reply_action' => true,
            'show_mark_read_action' => $markReadUrl !== null,
            'mark_read_url' => $markReadUrl,
            'create_contact_url' => $matched
                ? null
                : IngressCreateContactUrl::forPhone($fromPhone, conversationId: $conversationId),
            'has_attachment' => $hasMedia,
            'platform_message_public_id' => $platformMessageId,
        ];

        if ($conversationId !== null) {
            $interrupt['conversation_id'] = $conversationId;
        }

        if ($platformConversationId !== null) {
            $interrupt['platform_conversation_public_id'] = $platformConversationId;
        }

        return $interrupt;
    }

    public static function publicId(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $id = trim((string) $value);

        if ($id === '') {
            return null;
        }

        if (ctype_digit($id)) {
            return null;
        }

        return $id;
    }

    private static function snippet(string $body): string
    {
        $snippet = trim($body);

        if ($snippet === '') {
            return '(text message)';
        }

        if (mb_strlen($snippet) > 120) {
            return mb_substr($snippet, 0, 117).'…';
        }

        return $snippet;
    }

    private static function replyUrl(?int $conversationId, ?string $customerUrl, ?string $platformConversationId): ?string
    {
        if (filled($customerUrl)) {
            return $customerUrl.'?compose=text#customer-communication';
        }

        if ($conversationId !== null && Route::has('operations.conversations.reply')) {
            return route('operations.conversations.reply', $conversationId).'?compose=text#conversation-composer';
        }

        if ($platformConversationId !== null && Route::has('operations.communications.inbox')) {
            return route('operations.communications.inbox', [
                'filter' => 'needs',
                'platform_conversation' => $platformConversationId,
            ]);
        }

        return null;
    }
}
