<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Observations\CustomerRepliedObservationEmitter;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SendOutboundMessageAction
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationLinker $linker,
        private readonly ConversationMessageBroadcaster $broadcaster,
        private readonly CustomerRepliedObservationEmitter $customerRepliedObservations,
        private readonly OutboundAttachmentStore $attachments,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
    ) {}

    /**
     * @param  list<string>  $mediaUrls
     * @param  array<string, mixed>  $metadata
     * @return array{message: ConversationMessage, provider_message_sid: string}
     */
    public function execute(
        Customer $customer,
        User $actor,
        string $body,
        ?RepairOrder $repairOrder = null,
        ?UploadedFile $attachment = null,
        ?Conversation $conversation = null,
        array $metadata = [],
        ?string $toPhone = null,
    ): array {
        if (! $this->transport->isConfigured()) {
            throw new RuntimeException('Outbound SMS is not configured.');
        }

        $eligibility = CustomerSmsSendEligibility::for($customer, $this->credentials);

        if ($blockReason = $eligibility->blockReason()) {
            throw new RuntimeException($blockReason);
        }

        $destinationPhone = filled($toPhone) ? $toPhone : $customer->phone;

        if (! filled($destinationPhone)) {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        $this->smsCapability->assertCapableOrFail((string) $destinationPhone);

        $storedAttachment = $attachment ? $this->attachments->store($attachment) : null;
        $messageBody = trim($body);

        if ($messageBody === '' && $storedAttachment !== null) {
            $messageBody = '(attachment)';
        }

        if ($messageBody === '') {
            throw new RuntimeException('Enter a message or attach a file.');
        }

        $mediaUrls = $storedAttachment !== null ? [$storedAttachment['public_url']] : [];

        $result = $this->transport->send((string) $destinationPhone, $messageBody, $mediaUrls);

        $customer->forceFill([
            'last_sms_delivery_status' => $result->status !== '' ? $result->status : 'queued',
        ])->save();

        $message = $this->recordOutboundMessage(
            customer: $customer,
            actor: $actor,
            body: $messageBody,
            providerMessageSid: $result->messageId,
            repairOrder: $repairOrder,
            conversation: $conversation,
            metadata: $metadata,
        );

        if ($storedAttachment !== null) {
            ConversationMessageAttachment::query()->create([
                'conversation_message_id' => $message->id,
                'content_type' => $storedAttachment['content_type'],
                'storage_path' => $storedAttachment['storage_path'],
                'byte_size' => $storedAttachment['byte_size'],
            ]);

            $message->load('attachments');
        }

        $this->broadcaster->broadcast($message);

        $this->customerRepliedObservations->resolveAfterShopReply($message, $actor);

        return [
            'message' => $message,
            'provider_message_sid' => $result->messageId,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordOutboundMessage(
        Customer $customer,
        User $actor,
        string $body,
        string $providerMessageSid,
        ?RepairOrder $repairOrder,
        ?Conversation $conversation,
        array $metadata = [],
    ): ConversationMessage {
        if ($conversation !== null) {
            abort_unless(
                $conversation->contact_surface === ConversationContactSurface::Phone,
                422,
                'Only phone conversations can receive SMS replies.',
            );

            $this->linker->link($conversation, $customer);

            if ($repairOrder !== null) {
                $this->linker->linkRepairOrderContext($conversation, $repairOrder);
            }

            return $this->recorder->recordOutboundSmsToConversation(
                conversation: $conversation,
                actor: $actor,
                body: $body,
                providerMessageSid: $providerMessageSid,
                repairOrder: $repairOrder,
                metadata: $metadata,
            );
        }

        return $this->recorder->recordOutboundSms(
            customer: $customer,
            actor: $actor,
            body: $body,
            providerMessageSid: $providerMessageSid,
            repairOrder: $repairOrder,
            metadata: $metadata,
        );
    }
}
