<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommsInterruptBroadcast;
use App\Ark\Operations\Communications\CommunicationsMessageQueuePresenter;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerHubCommsTimeline;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

class ConversationMessageBroadcaster
{
    public function __construct(
        private readonly ConversationMessagePresenter $presenter,
        private readonly ConversationMessageRenderer $renderer,
        private readonly CommunicationsMessageQueuePresenter $queuePresenter,
        private readonly CommsInterruptBroadcast $interruptBroadcast,
        private readonly CustomerHubCommsTimeline $hubCommsTimeline,
    ) {}

    public function broadcast(
        ConversationMessage $message,
        ?CustomerCallContext $context = null,
    ): void {
        if (! ConversationBroadcast::enabled()) {
            return;
        }

        $message->loadMissing(['conversation', 'participant']);

        $customerId = $context?->customer?->id
            ?? $message->participant->customer_id
            ?? ConversationLink::query()
                ->where('conversation_id', $message->conversation_id)
                ->where('linkable_type', Customer::class)
                ->value('linkable_id')
            ?? $this->customerIdForConversation($message->conversation);

        $payload = [
            'message_id' => $message->id,
            'message' => $this->presenter->present($message),
            'html' => $this->renderer->render($message, 'border-t border-slate-100'),
            'customer_id' => $customerId,
            'hub_filter' => $this->hubCommsTimeline->filterForMessage($message),
            'normalized_phone' => $context?->normalizedPhone ?? $message->conversation->contact_address,
            'open_repair_order_ids' => $context?->openRepairOrders
                ->map(fn ($openRepairOrder): int => $openRepairOrder->repairOrder->repair_order_id)
                ->values()
                ->all() ?? [],
        ];

        if ($message->direction === OperationalCommunicationDirection::Inbound) {
            $payload['interrupt'] = $this->queuePresenter->present($message, $context, unread: true);
            $this->safeBroadcast(
                fn (): mixed => $this->interruptBroadcast->show(
                    (string) ($payload['interrupt']['kind'] ?? 'sms'),
                    $payload['interrupt'],
                ),
                $message->id,
                'comms interrupt show',
            );
        }

        $this->safeBroadcast(
            fn (): mixed => ConversationMessageReceived::dispatch($payload),
            $message->id,
            'conversation message received',
        );
    }

    private function safeBroadcast(callable $callback, int $messageId, string $context): void
    {
        try {
            $callback();
        } catch (BroadcastException $exception) {
            $this->logBroadcastFailure($messageId, $context, $exception);
        } catch (\Throwable $exception) {
            if (! $this->isBroadcastFailure($exception)) {
                throw $exception;
            }

            $this->logBroadcastFailure($messageId, $context, $exception);
        }
    }

    private function isBroadcastFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pusher error')
            || str_contains($message, 'broadcast');
    }

    private function logBroadcastFailure(int $messageId, string $context, \Throwable $exception): void
    {
        Log::warning('Conversation broadcast failed; message authority preserved.', [
            'context' => $context,
            'conversation_message_id' => $messageId,
            'message' => $exception->getMessage(),
        ]);
    }

    private function customerIdForConversation(Conversation $conversation): ?int
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return null;
        }

        $normalizedPhone = PhoneNumber::normalize((string) $conversation->contact_address);

        if ($normalizedPhone === null) {
            return null;
        }

        return Customer::query()
            ->where('phone', $normalizedPhone)
            ->value('id');
    }
}
