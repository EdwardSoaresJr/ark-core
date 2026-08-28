<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationIngress;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Conversations\MessengerCustomerResolver;
use App\Ark\Operations\Messaging\ConversationMessageBroadcaster;

class MetaMessengerIngress implements ConversationIngress
{
    public function __construct(
        private readonly MessengerCustomerResolver $customerResolver,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationMessageBroadcaster $broadcaster,
        private readonly MetaMessengerMediaFetcher $mediaFetcher,
    ) {}

    /**
     * @return array{message: ?ConversationMessage, context: ?CustomerCallContext, created: bool}
     */
    public function ingest(
        InboundConversationPayload $payload,
        ?MetaMessengerConfiguration $configuration = null,
    ): array {
        if (! $payload->isProcessable() || $payload->contactSurface !== ConversationContactSurface::Messenger) {
            return ['message' => null, 'context' => null, 'created' => false];
        }

        $configuration ??= MetaMessengerConfiguration::current();

        $existing = $this->findExistingMessage($payload->providerMessageId);

        if ($existing) {
            $customer = $this->customerResolver->forPsid($payload->contactKey);

            return [
                'message' => $existing,
                'context' => $customer ? $this->callContextResolver->resolveForCustomer($customer) : null,
                'created' => false,
            ];
        }

        $customer = $this->customerResolver->forPsid($payload->contactKey);

        $pageId = filled($payload->metadata['page_id'] ?? null)
            ? trim((string) $payload->metadata['page_id'])
            : null;

        $message = $this->recorder->recordInboundMessenger(
            psid: $payload->contactKey,
            body: $payload->body,
            providerMessageId: $payload->providerMessageId,
            customer: $customer,
            mediaCount: $payload->media !== [] ? count($payload->media) : null,
            pageId: $pageId,
        );

        if ($payload->media !== []) {
            $this->mediaFetcher->attachToMessage(
                $message,
                $payload->media,
                $configuration,
            );
            $message->load('attachments');
        }

        $context = $customer ? $this->callContextResolver->resolveForCustomer($customer) : null;

        $this->broadcaster->broadcast($message, $context);

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
                    ->orWhere('metadata->meta_message_id', $providerMessageId);
            })
            ->first();
    }
}
