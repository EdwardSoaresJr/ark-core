<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\Messenger\MessengerMessagingWindow;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerAttachmentType;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerConfiguration;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerSender;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SendOutboundMessengerAction
{
    public function __construct(
        private readonly MetaMessengerSender $sender,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationMessageBroadcaster $broadcaster,
        private readonly MessengerMessagingWindow $messagingWindow,
        private readonly OutboundAttachmentStore $attachments,
    ) {}

    /**
     * @return array{message: ConversationMessage, provider_message_id: string}
     */
    public function execute(
        Customer $customer,
        User $actor,
        string $body,
        ?RepairOrder $repairOrder = null,
        ?MetaMessengerMessageTag $messageTag = null,
        ?UploadedFile $attachment = null,
    ): array {
        $configuration = MetaMessengerConfiguration::current();

        if (! $configuration->isEnabled() || ! $configuration->shopConnection()->isConfigured()) {
            throw new RuntimeException('Messenger Page connection is not configured.');
        }

        $psid = trim((string) $customer->messenger_psid);

        if ($psid === '') {
            throw new RuntimeException('Customer does not have a linked Messenger profile.');
        }

        $storedAttachment = $attachment ? $this->attachments->store($attachment) : null;
        $messageBody = trim($body);

        if ($messageBody === '' && $storedAttachment === null) {
            throw new RuntimeException('Enter a message or attach a file.');
        }

        if ($messageBody === '' && $storedAttachment !== null) {
            $messageBody = '(attachment)';
        }

        $sendRequest = $this->messagingWindow->resolveSendRequest(
            $psid,
            $messageTag,
            $configuration->outsideWindowTag(),
        );

        $attachmentType = $storedAttachment !== null
            ? MetaMessengerAttachmentType::fromMimeType($storedAttachment['content_type'])
            : null;

        $result = $this->sender->send(
            $psid,
            $messageBody === '(attachment)' ? '' : $messageBody,
            $configuration,
            $sendRequest,
            $storedAttachment !== null ? $storedAttachment['public_url'] : null,
            $attachmentType,
        );

        $message = $this->recorder->recordOutboundMessenger(
            customer: $customer,
            actor: $actor,
            body: $messageBody,
            providerMessageId: $result->messageId,
            repairOrder: $repairOrder,
            metadata: array_filter([
                'messenger_message_tag' => $sendRequest->tag?->value,
                'messenger_send_mode' => strtolower($sendRequest->messagingType),
                'has_attachment' => $storedAttachment !== null ? true : null,
            ]),
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

        return [
            'message' => $message,
            'provider_message_id' => $result->messageId,
        ];
    }
}
