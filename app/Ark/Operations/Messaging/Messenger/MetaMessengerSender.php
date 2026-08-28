<?php

namespace App\Ark\Operations\Messaging\Messenger;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MetaMessengerSendResult
{
    public function __construct(
        public readonly string $messageId,
    ) {}
}

class MetaMessengerSender
{
    public function send(
        string $psid,
        string $body,
        MetaMessengerConfiguration $configuration,
        ?MetaMessengerSendRequest $sendRequest = null,
        ?string $attachmentUrl = null,
        ?MetaMessengerAttachmentType $attachmentType = null,
    ): MetaMessengerSendResult {
        $sendRequest ??= MetaMessengerSendRequest::response();

        $token = $configuration->pageAccessToken();

        if (! filled($token)) {
            throw new RuntimeException('Messenger page access token is not configured.');
        }

        $message = [];

        if ($attachmentUrl !== null && $attachmentType !== null) {
            $message['attachment'] = [
                'type' => $attachmentType->value,
                'payload' => [
                    'url' => $attachmentUrl,
                    'is_reusable' => true,
                ],
            ];
        }

        if (trim($body) !== '') {
            $message['text'] = $body;
        }

        if ($message === []) {
            throw new RuntimeException('Enter a message or attach a file.');
        }

        $payload = [
            'recipient' => ['id' => $psid],
            'messaging_type' => $sendRequest->messagingType,
            'message' => $message,
        ];

        if ($sendRequest->tag !== null) {
            $payload['tag'] = $sendRequest->tag->value;
        }

        $version = $configuration->graphVersion();
        $pageId = (string) ($configuration->pageId() ?? '');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post('https://graph.facebook.com/'.$version.'/me/messages', $payload);

            if (! $response->successful()) {
                $error = $response->json('error.message') ?? $response->body();

                if ($pageId !== '') {
                    MessengerHealth::rememberOutboundFailure($pageId);
                }

                throw new RuntimeException('Messenger send failed: '.$error);
            }

            $messageId = trim((string) ($response->json('message_id') ?? ''));

            if ($messageId === '') {
                if ($pageId !== '') {
                    MessengerHealth::rememberOutboundFailure($pageId);
                }

                throw new RuntimeException('Messenger send returned no message id.');
            }

            if ($pageId !== '') {
                MessengerHealth::rememberOutboundSuccess($pageId);
            }

            return new MetaMessengerSendResult(messageId: $messageId);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($pageId !== '') {
                MessengerHealth::rememberOutboundFailure($pageId);
            }

            throw $e;
        }
    }
}
