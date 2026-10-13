<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\PhoneNumber;

/**
 * Turns a verified Twilio inbound SMS or MMS into the existing conversation record.
 */
final class ReceiveTwilioInboundMessage
{
    public function __construct(
        private readonly InboundSmsConversationIngress $ingress,
        private readonly ProcessInboundSmsConsentAction $consent,
        private readonly ProcessMessageActionReplyAction $messageActions,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{confirmation: ?string}
     */
    public function receive(array $params): array
    {
        $sid = trim((string) ($params['MessageSid'] ?? ''));
        $from = PhoneNumber::normalize((string) ($params['From'] ?? ''));
        if ($sid === '' || $from === null) {
            return ['confirmation' => null];
        }

        $body = trim((string) ($params['Body'] ?? ''));
        $consent = $this->consent->execute($from, $body, $sid);
        if ($consent['handled']) {
            return ['confirmation' => $consent['confirmation']];
        }

        $media = $this->media($params);
        $payload = new InboundConversationPayload(
            contactSurface: ConversationContactSurface::Phone,
            contactKey: $from,
            providerMessageId: $sid,
            channel: OperationalCommunicationChannel::Sms,
            body: trim((string) ($params['Body'] ?? '')),
            media: $media,
            metadata: array_filter([
                'to_number' => PhoneNumber::normalize((string) ($params['To'] ?? '')),
                'source' => 'twilio',
            ]),
        );
        $action = $this->messageActions->execute($payload);
        if (! $action['handled']) {
            $this->ingress->ingest($payload);
        }

        return ['confirmation' => $action['confirmation']];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array{url: string, content_type: string, provider_media_sid: ?string}>
     */
    public function media(array $params): array
    {
        $count = (int) ($params['NumMedia'] ?? 0);
        $media = [];
        for ($index = 0; $index < $count; $index++) {
            $url = trim((string) ($params['MediaUrl'.$index] ?? ''));
            if ($url === '') {
                continue;
            }
            if (! self::allowedMediaUrl($url)) {
                throw new \InvalidArgumentException('Messaging media is not from Twilio.');
            }
            $media[] = [
                'url' => $url,
                'content_type' => trim((string) ($params['MediaContentType'.$index] ?? 'application/octet-stream')),
                'provider_media_sid' => null,
            ];
        }

        return $media;
    }

    public static function allowedMediaUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https' && in_array($host, ['api.twilio.com', 'mms.twilio.com'], true);
    }
}
