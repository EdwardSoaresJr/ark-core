<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use Illuminate\Http\Request;

class MetaMessengerInboundParser
{
    /**
     * @return list<InboundConversationPayload>
     */
    public function parse(Request $request): array
    {
        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'page') {
            return [];
        }

        $messages = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->parseMessagesFromEntry($entry) as $parsed) {
                $messages[] = $parsed;
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<InboundConversationPayload>
     */
    public function parseMessagesFromEntry(array $entry): array
    {
        $pageId = trim((string) ($entry['id'] ?? ''));
        $messages = [];

        foreach ($entry['messaging'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $parsed = $this->parseMessagingEvent($event, $pageId);

            if ($parsed !== null) {
                $messages[] = $parsed;
            }
        }

        return $messages;
    }

    /**
     * @return list<MetaMessengerReceiptPayload>
     */
    public function parseReceipts(Request $request): array
    {
        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'page') {
            return [];
        }

        $receipts = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->parseReceiptsFromEntry($entry) as $parsed) {
                $receipts[] = $parsed;
            }
        }

        return $receipts;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<MetaMessengerReceiptPayload>
     */
    public function parseReceiptsFromEntry(array $entry): array
    {
        $receipts = [];

        foreach ($entry['messaging'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $parsed = $this->parseReceiptEvent($event);

            if ($parsed !== null) {
                $receipts[] = $parsed;
            }
        }

        return $receipts;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function parseReceiptEvent(array $event): ?MetaMessengerReceiptPayload
    {
        $psid = trim((string) ($event['sender']['id'] ?? ''));

        if ($psid === '') {
            return null;
        }

        if (isset($event['delivery']) && is_array($event['delivery'])) {
            $mids = collect($event['delivery']['mids'] ?? [])
                ->filter(fn ($mid): bool => is_string($mid) && trim($mid) !== '')
                ->map(fn (string $mid): string => trim($mid))
                ->values()
                ->all();

            if ($mids === []) {
                return null;
            }

            return new MetaMessengerReceiptPayload(
                psid: $psid,
                kind: 'delivery',
                messageIds: $mids,
                watermark: isset($event['delivery']['watermark']) ? (int) $event['delivery']['watermark'] : null,
            );
        }

        if (isset($event['read']) && is_array($event['read'])) {
            return new MetaMessengerReceiptPayload(
                psid: $psid,
                kind: 'read',
                watermark: isset($event['read']['watermark']) ? (int) $event['read']['watermark'] : null,
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function parseMessagingEvent(array $event, string $pageId): ?InboundConversationPayload
    {
        if (isset($event['delivery']) || isset($event['read'])) {
            return null;
        }

        $message = is_array($event['message'] ?? null) ? $event['message'] : null;

        if ($message === null) {
            return null;
        }

        $psid = trim((string) ($event['sender']['id'] ?? ''));

        if ($psid === '') {
            return null;
        }

        $providerMessageId = trim((string) ($message['mid'] ?? ''));

        if ($providerMessageId === '') {
            return null;
        }

        $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
        $media = $this->parseAttachments($attachments);
        $body = trim((string) ($message['text'] ?? ''));

        if ($body === '' && $media !== []) {
            $body = '(attachment)';
        }

        return new InboundConversationPayload(
            contactSurface: ConversationContactSurface::Messenger,
            contactKey: $psid,
            providerMessageId: $providerMessageId,
            channel: OperationalCommunicationChannel::Messenger,
            body: $body,
            media: $media,
            metadata: array_filter([
                'provider' => 'meta_messenger',
                'page_id' => $pageId !== '' ? $pageId : null,
                'timestamp' => isset($event['timestamp']) ? (string) $event['timestamp'] : null,
                'media_count' => $media !== [] ? count($media) : null,
            ]),
            contactDisplay: null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return list<array{url: string, content_type: string, provider_media_sid: ?string}>
     */
    private function parseAttachments(array $attachments): array
    {
        $media = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $payload = is_array($attachment['payload'] ?? null) ? $attachment['payload'] : [];
            $url = trim((string) ($payload['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $media[] = [
                'url' => $url,
                'content_type' => $this->contentTypeFor((string) ($attachment['type'] ?? '')),
                'provider_media_sid' => null,
            ];
        }

        return $media;
    }

    private function contentTypeFor(string $type): string
    {
        return match ($type) {
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'audio' => 'audio/mpeg',
            'file' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
