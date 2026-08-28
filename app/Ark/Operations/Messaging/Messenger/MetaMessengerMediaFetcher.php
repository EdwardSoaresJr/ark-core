<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MetaMessengerMediaFetcher
{
    /**
     * @param  list<array{url: string, content_type: string, provider_media_sid: ?string}>  $media
     * @return list<ConversationMessageAttachment>
     */
    public function attachToMessage(ConversationMessage $message, array $media, MetaMessengerConfiguration $configuration): array
    {
        if ($media === []) {
            return [];
        }

        $token = $configuration->pageAccessToken();
        $attachments = [];

        foreach ($media as $index => $item) {
            $contentType = $item['content_type'];
            $storagePath = null;
            $byteSize = null;
            $downloadUrl = $this->authenticatedUrl($item['url'], $token);

            $response = Http::timeout(20)->get($downloadUrl);

            if ($response->successful()) {
                $resolvedType = $response->header('Content-Type') ?: $contentType;
                $contentType = trim(explode(';', (string) $resolvedType)[0]) ?: $contentType;
                $extension = $this->extensionFor($contentType);
                $storagePath = sprintf(
                    'conversation-media/%d/%d%s',
                    $message->id,
                    $index,
                    $extension,
                );

                Storage::disk('local')->put($storagePath, $response->body());
                $byteSize = strlen($response->body());
            }

            $attachments[] = ConversationMessageAttachment::query()->create([
                'conversation_message_id' => $message->id,
                'content_type' => $contentType,
                'storage_path' => $storagePath,
                'provider_url' => $item['url'],
                'provider_media_sid' => $item['provider_media_sid'],
                'byte_size' => $byteSize,
            ]);
        }

        return $attachments;
    }

    private function authenticatedUrl(string $url, ?string $token): string
    {
        if (! filled($token) || str_contains($url, 'access_token=')) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'access_token='.urlencode((string) $token);
    }

    private function extensionFor(string $contentType): string
    {
        return match (true) {
            str_starts_with($contentType, 'image/jpeg') => '.jpg',
            str_starts_with($contentType, 'image/png') => '.png',
            str_starts_with($contentType, 'image/gif') => '.gif',
            str_starts_with($contentType, 'image/webp') => '.webp',
            str_starts_with($contentType, 'video/') => '.mp4',
            $contentType === 'audio/mpeg', str_starts_with($contentType, 'audio/mp') => '.mp3',
            $contentType === 'application/pdf' => '.pdf',
            str_starts_with($contentType, 'audio/') => '.audio',
            default => '.bin',
        };
    }
}
