<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationIngress;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Leads\LeadReconciler;
use App\Ark\Operations\Observations\CustomerRepliedObservationEmitter;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;

/**
 * Fabric / Platform SMS → Core Conversation mirror (Track C compat bridge).
 *
 * Deletion condition: ManagedCommunicationsGate::coreMirrorEnabled() false after Stage 10.
 */
final class InboundSmsConversationIngress implements ConversationIngress
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationMessageBroadcaster $broadcaster,
        private readonly LeadReconciler $leadReconciler,
        private readonly CustomerRepliedObservationEmitter $customerRepliedObservations,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
        private readonly ConversationWork $conversationWork,
    ) {}

    /**
     * @return array{message: ?ConversationMessage, context: ?CustomerCallContext, created: bool}
     */
    public function ingest(InboundConversationPayload $payload, array $extraMetadata = []): array
    {
        if (! ManagedCommunicationsGate::coreMirrorEnabled()) {
            $context = $payload->isProcessable()
                ? $this->callContextResolver->resolve($payload->contactKey)
                : null;

            if ($payload->isProcessable()) {
                $conversation = $this->conversationWork->ensureForPhone($payload->contactKey, $context?->customer);
                $this->conversationWork->markNeedsAttention($conversation);
            }

            return ['message' => null, 'context' => $context, 'created' => false];
        }

        if (! $payload->isProcessable() || $payload->contactSurface !== ConversationContactSurface::Phone) {
            return ['message' => null, 'context' => null, 'created' => false];
        }

        $existing = $this->findExistingMessage($payload->providerMessageId);
        if ($existing) {
            return [
                'message' => $existing,
                'context' => $this->callContextResolver->resolve($payload->contactKey),
                'created' => false,
            ];
        }

        $context = $this->callContextResolver->resolve($payload->contactKey);

        $message = $this->recorder->recordInboundSms(
            normalizedPhone: $payload->contactKey,
            body: $payload->body,
            providerMessageSid: $payload->providerMessageId,
            customer: $context?->customer,
            media: [],
            toNumber: isset($payload->metadata['to_number']) ? (string) $payload->metadata['to_number'] : null,
            metadata: array_merge($extraMetadata, array_filter([
                'platform_message_public_id' => $payload->metadata['message_public_id'] ?? null,
                'platform_conversation_public_id' => $payload->metadata['conversation_public_id'] ?? null,
                'source' => $payload->metadata['source'] ?? 'ark_platform_texting',
            ])),
        );

        foreach ($payload->media as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? $item['provider_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            ConversationMessageAttachment::query()->create([
                'conversation_message_id' => $message->id,
                'content_type' => (string) ($item['content_type'] ?? 'application/octet-stream'),
                'storage_path' => null,
                'provider_url' => $url,
                'provider_media_sid' => filled($item['provider_media_sid'] ?? null)
                    ? (string) $item['provider_media_sid']
                    : null,
                'byte_size' => isset($item['byte_size']) ? (int) $item['byte_size'] : null,
            ]);
        }

        if ($payload->media !== []) {
            $message->load('attachments');
        }

        $this->leadReconciler->reconcileInboundSms($message, $context?->customer);
        $this->smsCapability->markCapableFromInboundSms($payload->contactKey);
        $this->broadcaster->broadcast($message, $context);
        $this->customerRepliedObservations->emitFromInboundMessage($message, $context?->customer?->id);

        return [
            'message' => $message,
            'context' => $context,
            'created' => true,
        ];
    }

    private function findExistingMessage(string $providerMessageId): ?ConversationMessage
    {
        if ($providerMessageId === '') {
            return null;
        }

        return ConversationMessage::query()
            ->where(function ($query) use ($providerMessageId): void {
                $query->where('metadata->provider_message_id', $providerMessageId)
                    ->orWhere('metadata->twilio_message_sid', $providerMessageId);
            })
            ->first();
    }
}
