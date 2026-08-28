<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationIngress;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Leads\LeadReconciler;
use App\Ark\Operations\Observations\CustomerRepliedObservationEmitter;

class TwilioSmsIngress implements ConversationIngress
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationRecorder $recorder,
        private readonly TwilioMediaFetcher $mediaFetcher,
        private readonly ConversationMessageBroadcaster $broadcaster,
        private readonly LeadReconciler $leadReconciler,
        private readonly CustomerRepliedObservationEmitter $customerRepliedObservations,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
    ) {}

    /**
     * @param  array<string, mixed>  $extraMetadata
     * @return array{message: ?ConversationMessage, context: ?CustomerCallContext, created: bool}
     */
    public function ingest(InboundConversationPayload $payload, array $extraMetadata = []): array
    {
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
            media: $payload->media,
            toNumber: isset($payload->metadata['to_number']) ? (string) $payload->metadata['to_number'] : null,
            metadata: $extraMetadata,
        );

        if ($payload->media !== []) {
            $this->mediaFetcher->attachToMessage($message, $payload->media);
            $message->load('attachments');
        }

        $this->leadReconciler->reconcileInboundSms($message, $context?->customer);

        $this->smsCapability->markCapableFromInboundSms($payload->contactKey);

        $this->broadcaster->broadcast($message, $context);

        $this->customerRepliedObservations->emitFromInboundMessage(
            $message,
            $context?->customer?->id,
        );

        return [
            'message' => $message,
            'context' => $context,
            'created' => true,
        ];
    }

    private function findExistingMessage(string $providerMessageId): ?ConversationMessage
    {
        return ConversationMessage::query()
            ->where(function ($query) use ($providerMessageId): void {
                $query->where('metadata->provider_message_id', $providerMessageId)
                    ->orWhere('metadata->twilio_message_sid', $providerMessageId);
            })
            ->first();
    }
}
