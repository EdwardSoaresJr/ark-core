<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Conversations\MessengerCustomerResolver;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class ConversationWorkboardPresenter
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly MessengerCustomerResolver $messengerCustomers,
        private readonly CommunicationsMessageQueuePresenter $messagePresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Conversation $conversation, string $laneKind): array
    {
        $conversation->loadMissing(['owner:id,name', 'messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1)]);

        /** @var ConversationMessage|null $latest */
        $latest = $conversation->messages->first();
        $contactAddress = (string) $conversation->contact_address;
        $channel = $latest?->channel;

        if ($channel === OperationalCommunicationChannel::Messenger) {
            $customer = $this->messengerCustomers->forPsid($contactAddress);
            $context = $customer ? $this->callContextResolver->resolveForCustomer($customer) : null;
        } else {
            $context = $this->callContextResolver->resolve($contactAddress);
            $customer = $context?->customer;
        }

        $matched = $customer !== null;
        $headline = $matched ? (string) $customer->name : 'Unknown';
        $displayContact = $channel === OperationalCommunicationChannel::Messenger
            ? 'Messenger · '.Str::limit($contactAddress, 16, '…')
            : ($context?->displayPhone ?? PhoneNumber::display($contactAddress) ?? $contactAddress);

        $messageRow = $latest !== null
            ? $this->messagePresenter->present($latest, $context, unread: false)
            : [];

        $postureAt = $conversation->posture_changed_at ?? $conversation->updated_at;
        $ownerName = $conversation->owner?->name;

        return [
            'kind' => $laneKind,
            'conversation_id' => $conversation->id,
            'waiting_on' => $conversation->waiting_on?->value,
            'waiting_on_label' => $conversation->waiting_on?->label() ?? '',
            'owned_by_user_id' => $conversation->owned_by_user_id,
            'owned_by_name' => $ownerName,
            'owner_label' => $ownerName !== null ? "Owned by {$ownerName}" : 'Unowned',
            'posture_age_label' => $postureAt?->diffForHumans(short: true) ?? '',
            'posture_age_days' => $postureAt instanceof Carbon
                ? (int) $postureAt->diffInDays(now())
                : 0,
            'reopen_count' => $conversation->reopen_count,
            'resolved_at_label' => $conversation->resolved_at?->diffForHumans(short: true) ?? '',
            'channel_label' => $messageRow['channel_label'] ?? ($channel?->label() ?? 'Message'),
            'queue_tab' => $messageRow['queue_tab'] ?? CommunicationsSurfaceChannel::Sms->value,
            'headline' => $headline,
            'display_phone' => $displayContact,
            'snippet' => $messageRow['snippet'] ?? '',
            'matched' => $matched,
            'customer_id' => $customer?->id,
            'customer_url' => $matched ? route('operations.customers.show', $customer) : null,
            'context_summary' => $messageRow['context_summary'] ?? ($matched ? 'Customer' : 'Unmatched'),
            'primary_ro_url' => $messageRow['primary_ro_url'] ?? null,
            'reply_url' => $messageRow['reply_url'] ?? null,
            'intake_url' => $matched
                ? null
                : route('operations.intake.create', IntakeEntryQuery::fromInboundPhoneMessage(
                    $contactAddress,
                    (string) ($latest?->body ?? ''),
                )),
            'create_contact_url' => $matched || $channel === OperationalCommunicationChannel::Messenger
                ? null
                : IngressCreateContactUrl::forPhone($contactAddress, conversationId: $conversation->id),
            'show_resolve_action' => $conversation->status === ConversationStatus::Open
                && $laneKind !== 'recently_resolved',
            'resolve_url' => Route::has('operations.conversations.resolve')
                ? route('operations.conversations.resolve', $conversation)
                : null,
            'show_reply_action' => filled($messageRow['reply_url'] ?? null),
            'show_link_customer_action' => $channel === OperationalCommunicationChannel::Messenger
                && ! $matched
                && Route::has('operations.conversations.link-customer'),
            'link_customer_url' => Route::has('operations.conversations.link-customer')
                ? route('operations.conversations.link-customer', $conversation)
                : null,
            'state_label' => $laneKind === 'recently_resolved'
                ? 'Resolved · '.($conversation->resolved_at?->diffForHumans(short: true) ?? '')
                : ($conversation->waiting_on === ConversationWaitingOn::Customer
                    ? 'Waiting on customer'
                    : ($ownerName !== null ? 'Needs shop · '.$ownerName : 'Needs shop')),
            'occurred_at' => $latest?->occurred_at?->toIso8601String(),
        ];
    }
}
