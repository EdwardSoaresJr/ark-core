<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Communications\ConversationSmsIntelligenceSliceTouch;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\RecordLeadFirstContactAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

class ConversationRecorder
{
    public function __construct(
        private readonly ConversationResolver $resolver,
        private readonly ConversationLinker $linker,
        private readonly ConversationParticipantResolver $participants,
        private readonly ConversationPosture $posture,
    ) {}

    public function record(
        Conversation $conversation,
        ConversationParticipant $participant,
        OperationalCommunicationChannel $channel,
        OperationalCommunicationDirection $direction,
        string $body,
        ?Carbon $occurredAt = null,
        ?array $metadata = null,
    ): ConversationMessage {
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'conversation_participant_id' => $participant->id,
            'channel' => $channel,
            'direction' => $direction,
            'body' => trim($body),
            'occurred_at' => $occurredAt ?? now(),
            'metadata' => $metadata,
        ]);

        $this->applyPosture($conversation, $channel, $direction, $participant, $metadata);

        if ($channel === OperationalCommunicationChannel::Sms) {
            app(ConversationSmsIntelligenceSliceTouch::class)->touchFromMessage($message);
        }

        if ($direction === OperationalCommunicationDirection::Outbound) {
            $actorId = $metadata['actor_user_id'] ?? $participant->user_id;

            if ($actorId !== null) {
                $actor = User::query()->find($actorId);

                if ($actor instanceof User) {
                    app(RecordLeadFirstContactAction::class)->execute($message, $actor);
                }
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function applyPosture(
        Conversation $conversation,
        OperationalCommunicationChannel $channel,
        OperationalCommunicationDirection $direction,
        ConversationParticipant $participant,
        ?array $metadata,
    ): void {
        $metadata ??= [];

        // Website portal inbound participates in Turn precedence (phone Thread via forCustomer).
        if ($channel === OperationalCommunicationChannel::Website
            && $direction === OperationalCommunicationDirection::Inbound) {
            app(SyncConversationTurnAction::class)->execute($conversation->fresh());

            return;
        }

        if (! in_array($channel, [
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationChannel::Messenger,
        ], true)) {
            return;
        }

        if (($metadata['website_lead'] ?? false) === true) {
            app(SyncConversationTurnAction::class)->execute($conversation->fresh());

            return;
        }

        if ($direction === OperationalCommunicationDirection::Inbound) {
            $this->posture->recordInbound($conversation->fresh());

            return;
        }

        $actorId = $metadata['actor_user_id'] ?? $participant->user_id;

        if ($actorId === null) {
            return;
        }

        $actor = User::query()->find($actorId);

        if ($actor instanceof User) {
            $this->posture->recordOutbound($conversation->fresh(), $actor);
        }
    }

    public function recordRepairOrderEmail(
        RepairOrder $repairOrder,
        User $actor,
        string $recipientEmail,
        string $body,
        OperationalCommunicationChannel $channel = OperationalCommunicationChannel::Email,
        array $metadata = [],
    ): ConversationMessage {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $conversation = $this->resolver->forEmail($recipientEmail);
        $this->linker->linkRepairOrderContext($conversation, $repairOrder);

        $participant = $this->participants->system($conversation, displayName: 'Shop');

        return $this->record(
            $conversation,
            $participant,
            $channel,
            OperationalCommunicationDirection::Outbound,
            $body,
            metadata: array_filter(array_merge([
                'actor_user_id' => $actor->id,
                'repair_order_id' => $repairOrder->id,
            ], $metadata), fn (mixed $value): bool => $value !== null && $value !== []),
        );
    }

    public function recordAdvisorLog(
        RepairOrder $repairOrder,
        ?User $actor,
        OperationalCommunicationChannel $channel,
        OperationalCommunicationDirection $direction,
        string $body,
    ): ConversationMessage {
        $repairOrder->loadMissing('customer');

        $conversation = $this->resolver->forCustomer($repairOrder->customer);
        $this->linker->linkRepairOrderContext($conversation, $repairOrder);

        $participant = match (true) {
            $direction === OperationalCommunicationDirection::Inbound => $this->participants->customer(
                $conversation,
                $repairOrder->customer,
            ),
            $actor !== null => $this->participants->advisor($conversation, $actor),
            default => $this->participants->system($conversation, displayName: 'Shop'),
        };

        return $this->record($conversation, $participant, $channel, $direction, $body);
    }

    /**
     * @param  list<array{url: string, content_type: string, provider_media_sid: ?string}>  $media
     */
    /**
     * @param  list<array{url: string, content_type?: string|null}>  $media
     * @param  array<string, mixed>  $metadata
     */
    public function recordInboundSms(
        string $normalizedPhone,
        string $body,
        string $providerMessageSid,
        ?Customer $customer = null,
        array $media = [],
        ?string $toNumber = null,
        array $metadata = [],
    ): ConversationMessage {
        $conversation = $this->resolver->forContactKey(ConversationContactSurface::Phone, $normalizedPhone);

        if ($customer) {
            $this->linker->link($conversation, $customer);

            $customer->repairOrders()
                ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                ->with(['customer', 'vehicle'])
                ->get()
                ->each(fn (RepairOrder $repairOrder) => $this->linker->linkRepairOrderContext($conversation, $repairOrder));
        }

        $participant = $this->participants->customer(
            $conversation,
            $customer,
            displayName: $customer?->name,
        );

        $messageBody = trim($body);

        if ($messageBody === '' && $media !== []) {
            $messageBody = '(attachment)';
        }

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Inbound,
            $messageBody,
            metadata: array_filter(array_merge([
                'provider_message_id' => $providerMessageSid,
                'twilio_message_sid' => $providerMessageSid,
                'to_number' => $toNumber,
                'media_count' => $media !== [] ? count($media) : null,
            ], $metadata), fn (mixed $value): bool => $value !== null && $value !== []),
        );
    }

    public function recordInboundMessenger(
        string $psid,
        string $body,
        string $providerMessageId,
        ?Customer $customer = null,
        ?string $displayName = null,
        ?int $mediaCount = null,
        ?string $pageId = null,
    ): ConversationMessage {
        $conversation = $this->resolver->forContactKey(ConversationContactSurface::Messenger, $psid);

        if ($customer) {
            $this->linker->link($conversation, $customer);

            $customer->repairOrders()
                ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                ->with(['customer', 'vehicle'])
                ->get()
                ->each(fn (RepairOrder $repairOrder) => $this->linker->linkRepairOrderContext($conversation, $repairOrder));
        }

        $participant = $this->participants->customer(
            $conversation,
            $customer,
            displayName: $displayName ?: ($customer?->name ?: 'Messenger user'),
        );

        $messageBody = trim($body);

        if ($messageBody === '' && ($mediaCount ?? 0) > 0) {
            $messageBody = '(attachment)';
        }

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Messenger,
            OperationalCommunicationDirection::Inbound,
            $messageBody,
            metadata: array_filter([
                'provider_message_id' => $providerMessageId,
                'meta_message_id' => $providerMessageId,
                'media_count' => $mediaCount,
                'page_id' => filled($pageId) ? trim($pageId) : null,
            ]),
        );
    }

    public function recordOutboundMessenger(
        Customer $customer,
        User $actor,
        string $body,
        string $providerMessageId,
        ?RepairOrder $repairOrder = null,
        array $metadata = [],
    ): ConversationMessage {
        $psid = trim((string) $customer->messenger_psid);

        abort_if($psid === '', 422, 'Customer does not have a linked Messenger profile.');

        $conversation = $this->resolver->forContactKey(ConversationContactSurface::Messenger, $psid);
        $this->linker->link($conversation, $customer);

        if ($repairOrder) {
            $this->linker->linkRepairOrderContext($conversation, $repairOrder);
        }

        $participant = $this->participants->advisor($conversation, $actor);

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Messenger,
            OperationalCommunicationDirection::Outbound,
            trim($body),
            metadata: array_filter(array_merge([
                'provider_message_id' => $providerMessageId,
                'meta_message_id' => $providerMessageId,
                'actor_user_id' => $actor->id,
                'repair_order_id' => $repairOrder?->id,
            ], $metadata)),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordOutboundSmsToConversation(
        Conversation $conversation,
        User $actor,
        string $body,
        string $providerMessageSid,
        ?RepairOrder $repairOrder = null,
        array $metadata = [],
    ): ConversationMessage {
        abort_unless(
            $conversation->contact_surface === ConversationContactSurface::Phone,
            422,
            'Only phone conversations can receive SMS replies.',
        );

        $participant = $this->participants->advisor($conversation, $actor);

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            trim($body),
            metadata: array_filter(array_merge([
                'twilio_message_sid' => $providerMessageSid,
                'actor_user_id' => $actor->id,
                'repair_order_id' => $repairOrder?->id,
            ], $metadata), fn (mixed $value): bool => $value !== null && $value !== []),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordSystemOutboundSms(
        Conversation $conversation,
        string $body,
        string $providerMessageSid,
        array $metadata = [],
    ): ConversationMessage {
        abort_unless(
            $conversation->contact_surface === ConversationContactSurface::Phone,
            422,
            'Only phone conversations can receive SMS replies.',
        );

        $participant = $this->participants->system($conversation, displayName: 'Shop');

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            trim($body),
            metadata: array_filter(array_merge([
                'twilio_message_sid' => $providerMessageSid,
            ], $metadata)),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordSystemEmail(
        Conversation $conversation,
        string $recipientEmail,
        string $body,
        array $metadata = [],
    ): ConversationMessage {
        $participant = $this->participants->system($conversation, displayName: 'Shop');

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            trim($body),
            metadata: array_filter(array_merge([
                'recipient_email' => $recipientEmail,
            ], $metadata)),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordOutboundSms(
        Customer $customer,
        User $actor,
        string $body,
        string $providerMessageSid,
        ?RepairOrder $repairOrder = null,
        array $metadata = [],
    ): ConversationMessage {
        $conversation = $this->resolver->forCustomer($customer);

        if ($repairOrder) {
            $this->linker->linkRepairOrderContext($conversation, $repairOrder);
        } else {
            $this->linker->link($conversation, $customer);
        }

        $participant = $this->participants->advisor($conversation, $actor);

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            trim($body),
            metadata: array_filter(array_merge([
                'twilio_message_sid' => $providerMessageSid,
                'actor_user_id' => $actor->id,
                'repair_order_id' => $repairOrder?->id,
            ], $metadata), fn (mixed $value): bool => $value !== null && $value !== []),
        );
    }

    public function recordPortalEstimateView(RepairOrder $repairOrder, string $body): ConversationMessage
    {
        $repairOrder->loadMissing('customer');

        $conversation = $this->resolver->forCustomer($repairOrder->customer);
        $this->linker->linkRepairOrderContext($conversation, $repairOrder);

        $participant = $this->participants->customer(
            $conversation,
            $repairOrder->customer,
        );

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Website,
            OperationalCommunicationDirection::Inbound,
            $body,
            metadata: [
                'repair_order_id' => $repairOrder->id,
                'portal_estimate_view' => true,
            ],
        );
    }

    public function recordWebsiteLead(
        ?User $actor,
        string $body,
        ?string $phone = null,
        ?string $callbackName = null,
        ?string $referralSource = null,
    ): ConversationMessage {
        $conversation = $this->resolver->forWebsiteLead($phone);

        $displayName = filled($callbackName)
            ? trim((string) $callbackName)
            : 'Website visitor';

        $participant = $this->participants->customer(
            $conversation,
            displayName: $displayName,
        );

        $message = $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Website,
            OperationalCommunicationDirection::Inbound,
            $body,
            metadata: array_filter([
                'website_lead' => true,
                'referral_source' => $referralSource,
                'created_by_user_id' => $actor?->id,
            ]),
        );

        $this->posture->recordInbound($conversation->fresh());

        return $message;
    }

    public function recordInternalNote(Conversation $conversation, User $actor, string $body): ConversationMessage
    {
        $participant = $this->participants->advisor($conversation, $actor);

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Internal,
            OperationalCommunicationDirection::Internal,
            trim($body),
            metadata: [
                'actor_user_id' => $actor->id,
                'advisor_note' => true,
            ],
        );
    }

    public function recordCallNote(Conversation $conversation, User $actor, string $body, int $callSessionId): ConversationMessage
    {
        $participant = $this->participants->advisor($conversation, $actor);

        return $this->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Phone,
            OperationalCommunicationDirection::Internal,
            trim($body),
            metadata: [
                'actor_user_id' => $actor->id,
                'call_session_id' => $callSessionId,
                'call_note' => true,
            ],
        );
    }
}
