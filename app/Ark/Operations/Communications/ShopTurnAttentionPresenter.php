<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class ShopTurnAttentionPresenter
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationTurnReason $turnReason,
        private readonly CommunicationsMessageQueuePresenter $messagePresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Conversation $conversation, ?Lead $lead = null): array
    {
        $conversation->loadMissing(['owner:id,name', 'messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1)]);

        /** @var ConversationMessage|null $latest */
        $latest = $conversation->messages->first();
        $latest?->loadMissing('attachments');
        $contactAddress = (string) $conversation->contact_address;
        $context = $this->callContextResolver->resolveForAttentionList($contactAddress);
        $customer = $context?->customer;
        $matched = $customer !== null;

        $displayContactPreview = PhoneNumber::display($contactAddress) ?? $contactAddress;

        $headline = match (true) {
            filled($lead?->contact_name) => (string) $lead->contact_name,
            $matched => (string) $customer->name,
            default => $displayContactPreview,
        };

        $turn = $this->turnReason->for($conversation, $lead);
        $channelLabel = $this->channelLabel($latest, $lead);
        $kind = $this->queueKind($latest, $lead);

        $messageRow = $latest !== null
            ? $this->messagePresenter->present($latest, $context, unread: false)
            : [];

        $displayContact = $messageRow['display_phone']
            ?? PhoneNumber::display($contactAddress)
            ?? $contactAddress;

        $postureAt = $conversation->posture_changed_at ?? $conversation->updated_at;

        return array_merge($messageRow, [
            'kind' => $kind,
            'queue_tab' => $messageRow['queue_tab'] ?? ($lead?->source === LeadSource::Website
                ? CommunicationsSurfaceChannel::Portal->value
                : CommunicationsSurfaceChannel::Sms->value),
            'channel_label' => $channelLabel,
            'state' => $turn['state'],
            'state_label' => $turn['state_label'],
            'turn_label' => $turn['turn_label'],
            'conversation_id' => $conversation->id,
            'headline' => $headline,
            'display_phone' => $displayContact,
            'normalized_phone' => PhoneNumber::normalize($contactAddress),
            'snippet' => $messageRow['snippet'] ?? Str::limit((string) ($latest?->body ?? $lead?->concern ?? ''), 140),
            'matched' => $matched,
            'customer_id' => $customer?->id,
            'context_summary' => $messageRow['context_summary'] ?? ($lead !== null ? $lead->source->label() : 'Unmatched'),
            'age_label' => $postureAt?->diffForHumans(short: true) ?? '',
            'posture_age_label' => $postureAt?->diffForHumans(short: true) ?? '',
            'occurred_at' => ($latest?->occurred_at ?? $postureAt)?->toIso8601String(),
            'owned_by_name' => $conversation->owner?->name,
            'reply_url' => Route::has('operations.communications.inbox')
                ? CommunicationsNeedsYou::url(['conversation' => $conversation->id])
                : null,
            'show_reply_action' => true,
            'intake_url' => $matched
                ? null
                : ($lead !== null
                    ? route('operations.intake.create', IntakeEntryQuery::fromLead($lead))
                    : route('operations.intake.create', IntakeEntryQuery::fromInboundPhoneMessage(
                        $contactAddress,
                        (string) ($latest?->body ?? $lead?->concern ?? ''),
                    ))),
            'create_contact_url' => $matched
                ? null
                : ($lead !== null
                    ? IngressCreateContactUrl::forLead($lead)
                    : IngressCreateContactUrl::forPhone($contactAddress, conversationId: $conversation->id)),
            'lead_id' => $lead?->id,
            'website_lead' => $lead?->source === LeadSource::Website,
        ]);
    }

    private function channelLabel(?ConversationMessage $latest, ?Lead $lead): string
    {
        if ($lead?->source === LeadSource::Website) {
            return $lead->source->opportunityLabel();
        }

        if ($latest?->channel === OperationalCommunicationChannel::Website) {
            return LeadSource::Website->opportunityLabel();
        }

        if ($latest?->channel === OperationalCommunicationChannel::Sms
            && $latest->attachments->isNotEmpty()) {
            return 'MMS';
        }

        return $latest?->channel->label() ?? 'Message';
    }

    private function queueKind(?ConversationMessage $latest, ?Lead $lead): string
    {
        if ($lead?->source === LeadSource::Website || $latest?->channel === OperationalCommunicationChannel::Website) {
            return 'website_lead';
        }

        if ($latest?->channel === OperationalCommunicationChannel::Sms) {
            return $latest->attachments->isNotEmpty() ? 'mms' : 'sms';
        }

        return match ($latest?->channel) {
            OperationalCommunicationChannel::Messenger => 'messenger',
            default => 'message',
        };
    }
}
