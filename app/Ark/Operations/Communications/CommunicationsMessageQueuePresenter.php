<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Conversations\MessengerCustomerResolver;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerConfiguration;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class CommunicationsMessageQueuePresenter
{
        public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly MessengerCustomerResolver $messengerCustomers,
        private readonly Orientation $orientation,
        private readonly ConversationTurnReason $turnReason,
        private readonly ConversationLeadResolver $conversationLeads,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        ConversationMessage $message,
        ?CustomerCallContext $context = null,
        bool $unread = false,
    ): array {
        $message->loadMissing(['conversation', 'attachments', 'participant.customer']);

        $contactAddress = (string) ($message->conversation->contact_address ?? '');

        if ($message->channel === OperationalCommunicationChannel::Messenger) {
            $customer = $this->messengerCustomers->forPsid($contactAddress);
            $context = $customer ? $this->callContextResolver->resolveForCustomer($customer) : null;
        } else {
            $context ??= $this->callContextResolver->resolve($contactAddress);
            $customer = $context?->customer;
        }

        $matched = $customer !== null;
        $hasAttachments = $message->attachments->isNotEmpty();
        [$channelKind, $channelLabel] = match ($message->channel) {
            OperationalCommunicationChannel::Messenger => ['messenger', 'Messenger'],
            OperationalCommunicationChannel::Sms => $hasAttachments ? ['mms', 'MMS'] : ['sms', 'SMS'],
            default => [$message->channel->value, $message->channel->label()],
        };
        $phone = $message->channel === OperationalCommunicationChannel::Messenger ? '' : $contactAddress;
        $stateLabel = $this->stateLabel($message, $unread, $hasAttachments, $channelKind);
        $headline = $matched ? (string) $customer->name : 'Unknown';
        $canReply = match ($message->channel) {
            OperationalCommunicationChannel::Messenger => $matched && MetaMessengerConfiguration::current()->isConfigured(),
            OperationalCommunicationChannel::Sms => $message->direction === OperationalCommunicationDirection::Inbound,
            default => $matched && $this->credentials->twilioConfigured(),
        };
        $openRepairOrders = $this->openRepairOrders($context);
        $primaryOpenRepairOrder = $context?->openRepairOrders->first();
        $orientation = $primaryOpenRepairOrder !== null
            ? array_merge(
                $this->orientation->repairOrder($primaryOpenRepairOrder->repairOrder, OrientationDensity::Compact),
                ['density' => OrientationDensity::Compact->value],
            )
            : null;
        $customerUrl = $matched ? route('operations.customers.show', $customer) : null;
        $replyUrl = $this->replyUrl($message, $canReply, $openRepairOrders, $customerUrl);
        $displayContact = $message->channel === OperationalCommunicationChannel::Messenger
            ? 'Messenger · '.Str::limit($contactAddress, 16, '…')
            : ($context?->displayPhone ?? PhoneNumber::display($phone) ?? $phone);

        $snippetBody = trim((string) $message->body);
        if ($snippetBody === '' || $snippetBody === '(attachment)') {
            $snippetBody = match (true) {
                ! $hasAttachments => '',
                $message->attachments->count() > 1 => $message->attachments->count().' attachments',
                $message->attachments->first()?->isImage() => 'Photo',
                $message->attachments->first()?->isVideo() => 'Video',
                $message->attachments->first()?->isAudio() => 'Audio',
                $message->attachments->first()?->isPdf() => 'PDF',
                default => 'Attachment',
            };
        }

        return [
            'kind' => $channelKind,
            'queue_tab' => $this->queueTab($message),
            'channel' => $channelKind,
            'channel_label' => $channelLabel,
            'direction' => $message->direction->value,
            'direction_label' => $message->direction->queueLabel(),
            'state' => $unread ? 'unread' : $this->settledState($message),
            'state_label' => $stateLabel,
            'conversation_message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'headline' => $headline,
            'display_phone' => $displayContact,
            'normalized_phone' => $message->channel === OperationalCommunicationChannel::Messenger
                ? null
                : PhoneNumber::normalize($phone),
            'messenger_psid' => $message->channel === OperationalCommunicationChannel::Messenger ? $contactAddress : null,
            'snippet' => Str::limit($snippetBody, 140),
            'matched' => $matched,
            'customer_id' => $customer?->id,
            'context_summary' => $this->contextSummary($context, $openRepairOrders, $matched, $orientation),
            'orientation' => $orientation,
            'primary_ro_url' => count($openRepairOrders) === 1
                ? ($openRepairOrders[0]['url'] ?? null)
                : null,
            'open_ros_url' => count($openRepairOrders) > 1 && filled($customerUrl)
                ? $customerUrl.'#open-repair-orders'
                : null,
            'age_label' => $message->occurred_at?->diffForHumans(short: true) ?? '',
            'occurred_at' => $message->occurred_at?->toIso8601String(),
            'occurred_at_label' => $message->occurred_at
                ?->timezone(config('app.display_timezone'))
                ->format('M j, g:i A') ?? '',
            'lookup_url' => $message->channel === OperationalCommunicationChannel::Messenger
                ? null
                : route('operations.caller-lookup', ['phone' => $phone]),
            'customer_url' => $customerUrl,
            'intake_url' => $message->channel === OperationalCommunicationChannel::Messenger
                ? null
                : route('operations.intake.create', IntakeEntryQuery::fromInboundPhoneMessage(
                    $phone,
                    $message->direction === OperationalCommunicationDirection::Inbound
                        ? (string) $message->body
                        : '',
                )),
            'create_contact_url' => $matched || $message->channel === OperationalCommunicationChannel::Messenger
                ? null
                : IngressCreateContactUrl::forPhone($phone, conversationId: (int) $message->conversation_id),
            'reply_url' => $replyUrl,
            'show_reply_action' => filled($replyUrl),
            'show_link_customer_action' => $message->channel === OperationalCommunicationChannel::Messenger
                && ! $matched
                && Route::has('operations.conversations.link-customer'),
            'link_customer_url' => Route::has('operations.conversations.link-customer')
                ? route('operations.conversations.link-customer', $message->conversation_id)
                : null,
            'show_mark_read_action' => $unread,
            'mark_read_url' => Route::has('operations.conversations.read')
                ? route('operations.conversations.read', $message->conversation_id)
                : null,
            'has_attachment' => $hasAttachments,
            'attachment_count' => $message->attachments->count(),
            'attachments' => $message->attachments
                ->map(fn (ConversationMessageAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'content_type' => $attachment->content_type,
                    'url' => Route::has('operations.conversation-attachments.show')
                        ? route('operations.conversation-attachments.show', [
                            'conversation' => $message->conversation_id,
                            'message' => $message->id,
                            'attachment' => $attachment,
                        ])
                        : null,
                    'is_image' => $attachment->isImage(),
                    'is_video' => $attachment->isVideo(),
                    'is_audio' => $attachment->isAudio(),
                    'is_pdf' => $attachment->isPdf(),
                ])
                ->values()
                ->all(),
            'dropdown_label' => $unread
                ? "{$channelLabel} · {$headline}"
                : "{$channelLabel} · {$stateLabel} · {$headline}",
        ];
    }

    private function stateLabel(ConversationMessage $message, bool $unread, bool $hasAttachments, string $channelKind): string
    {
        if ($unread) {
            $lead = $this->conversationLeads->forTurn($message->conversation);

            $turn = $this->turnReason->for($message->conversation, $lead);

            if (in_array($turn['turn_label'], ['Customer replied', 'Needs first response'], true)) {
                return $turn['state_label'];
            }

            return match ($channelKind) {
                'messenger' => 'Messenger',
                'mms' => 'MMS',
                default => 'SMS',
            };
        }

        return $message->direction->value === 'outbound' ? 'Sent' : 'Read';
    }

    private function settledState(ConversationMessage $message): string
    {
        return $message->direction->value === 'outbound' ? 'sent' : 'read';
    }

    private function queueTab(ConversationMessage $message): string
    {
        if (($message->metadata['portal_estimate_view'] ?? false) === true) {
            return CommunicationsSurfaceChannel::Portal->value;
        }

        return match ($message->channel) {
            OperationalCommunicationChannel::Messenger => CommunicationsSurfaceChannel::Messenger->value,
            OperationalCommunicationChannel::Email => CommunicationsSurfaceChannel::Email->value,
            OperationalCommunicationChannel::Sms => CommunicationsSurfaceChannel::Sms->value,
            OperationalCommunicationChannel::Website => CommunicationsSurfaceChannel::Portal->value,
            default => $message->channel->value,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openRepairOrders(?CustomerCallContext $context): array
    {
        return $context?->openRepairOrders
            ->map(fn (CustomerCallContextOpenRepairOrder $openRepairOrder): array => [
                'repair_order_id' => $openRepairOrder->repairOrder->repair_order_id,
                'status_label' => $openRepairOrder->repairOrder->statusDisplayLabel(),
                'url' => Route::has('operations.repair-orders.show')
                    ? route('operations.repair-orders.show', $openRepairOrder->repairOrder)
                    : null,
            ])
            ->values()
            ->all() ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $openRepairOrders
     */
    private function replyUrl(
        ConversationMessage $message,
        bool $canReply,
        array $openRepairOrders,
        ?string $customerUrl,
    ): ?string {
        if (! $canReply) {
            return null;
        }

        if (count($openRepairOrders) === 1 && filled($openRepairOrders[0]['url'] ?? null)) {
            $url = (string) $openRepairOrders[0]['url'];

            if ($message->channel === OperationalCommunicationChannel::Messenger) {
                return $url.'#conversation-messages-ro';
            }

            return $url.'?compose=text#comms';
        }

        if (! filled($customerUrl)) {
            if (in_array($message->channel, [OperationalCommunicationChannel::Sms], true)) {
                return route('operations.conversations.reply', $message->conversation_id).'?compose=text#conversation-composer';
            }

            return null;
        }

        $compose = $message->channel === OperationalCommunicationChannel::Messenger ? 'messenger' : 'text';

        return $customerUrl.'?compose='.$compose.'#customer-communication';
    }

    /**
     * @param  array<int, array<string, mixed>>  $openRepairOrders
     */
    private function contextSummary(
        ?CustomerCallContext $context,
        array $openRepairOrders,
        bool $matched,
        ?array $orientation,
    ): string {
        if ($orientation !== null && filled($orientation['situation'] ?? null)) {
            return (string) $orientation['situation'];
        }

        if (count($openRepairOrders) === 1) {
            return 'RO '.$openRepairOrders[0]['status_label'];
        }

        if (count($openRepairOrders) > 1) {
            return count($openRepairOrders).' Open ROs';
        }

        if ($matched) {
            return (string) ($context?->customer?->customer_type ?? 'Customer');
        }

        return (string) ($context?->displayPhone ?? 'Unmatched');
    }
}
