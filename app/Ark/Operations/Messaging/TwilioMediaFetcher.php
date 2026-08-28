<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TwilioMediaFetcher
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @param  list<array{url: string, content_type: string, provider_media_sid: ?string}>  $media
     * @return list<ConversationMessageAttachment>
     */
    public function attachToMessage(ConversationMessage $message, array $media): array
    {
        if ($media === []) {
            return [];
        }

        $accountSid = $this->credentials->twilioAccountSid();
        $authToken = $this->credentials->twilioAuthToken();
        $attachments = [];

        foreach ($media as $index => $item) {
            $contentType = $item['content_type'];
            $storagePath = null;
            $byteSize = null;

            $client = filled($accountSid) && filled($authToken)
                ? Http::withBasicAuth($accountSid, $authToken)
                : null;

            if ($client === null && app()->environment('testing')) {
                $client = Http::baseUrl('');
            }

            if ($client !== null) {
                $response = $client->timeout(20)->get($item['url']);

                if ($response->successful()) {
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

    private function extensionFor(string $contentType): string
    {
        return match (true) {
            str_starts_with($contentType, 'image/jpeg') => '.jpg',
            str_starts_with($contentType, 'image/png') => '.png',
            str_starts_with($contentType, 'image/gif') => '.gif',
            str_starts_with($contentType, 'image/webp') => '.webp',
            str_starts_with($contentType, 'video/') => '.mp4',
            $contentType === 'audio/mpeg', str_starts_with($contentType, 'audio/mp') => '.mp3',
            $contentType === 'audio/amr' => '.amr',
            $contentType === 'audio/ogg' => '.ogg',
            $contentType === 'audio/wav', $contentType === 'audio/x-wav' => '.wav',
            $contentType === 'audio/aac' => '.aac',
            $contentType === 'audio/3gpp' => '.3gp',
            $contentType === 'application/pdf' => '.pdf',
            str_starts_with($contentType, 'audio/') => '.audio',
            default => '.bin',
        };
    }
}
