<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Http\Request;

class TwilioInboundMessageParser
{
    public function parse(Request $request): InboundConversationPayload
    {
        $from = (string) $request->input('From', '');
        $normalizedFrom = PhoneNumber::normalize($from) ?? '';

        $media = [];
        $mediaCount = (int) $request->input('NumMedia', 0);

        for ($index = 0; $index < $mediaCount; $index++) {
            $url = trim((string) $request->input("MediaUrl{$index}", ''));

            if ($url === '') {
                continue;
            }

            $media[] = [
                'url' => $url,
                'content_type' => trim((string) $request->input("MediaContentType{$index}", 'application/octet-stream')),
                'provider_media_sid' => $this->mediaSidFromUrl($url),
            ];
        }

        return new InboundConversationPayload(
            contactSurface: ConversationContactSurface::Phone,
            contactKey: $normalizedFrom,
            providerMessageId: trim((string) $request->input('MessageSid', '')),
            channel: OperationalCommunicationChannel::Sms,
            body: trim((string) $request->input('Body', '')),
            media: $media,
            metadata: array_filter([
                'to_number' => trim((string) $request->input('To', '')) ?: null,
                'provider' => 'twilio_sms',
            ]),
            contactDisplay: PhoneNumber::display($normalizedFrom) ?? $from,
        );
    }

    private function mediaSidFromUrl(string $url): ?string
    {
        if (! preg_match('#/Media/(ME[a-zA-Z0-9]+)#', $url, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
