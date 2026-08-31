<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SendOutboundContactMessageAction
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationLinker $linker,
        private readonly ConversationMessageBroadcaster $broadcaster,
        private readonly OutboundAttachmentStore $attachments,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @return array{message: ConversationMessage, provider_message_sid: string}
     */
    public function execute(
        Conversation $conversation,
        User $actor,
        string $body,
        ?UploadedFile $attachment = null,
    ): array {
        abort_unless(
            $conversation->contact_surface === ConversationContactSurface::Phone,
            422,
            'Only phone conversations can receive SMS replies.',
        );

        if (! $this->transport->isConfigured()) {
            throw new RuntimeException('Outbound SMS is not configured.');
        }

        $phone = trim((string) $conversation->contact_address);

        if ($phone === '') {
            throw new RuntimeException('Conversation does not have a phone number.');
        }

        $storedAttachment = $attachment ? $this->attachments->store($attachment) : null;
        $messageBody = trim($body);

        if ($messageBody === '' && $storedAttachment !== null) {
            $messageBody = '(attachment)';
        }

        if ($messageBody === '') {
            throw new RuntimeException('Enter a message or attach a file.');
        }

        $mediaUrls = $storedAttachment !== null ? [$storedAttachment['public_url']] : [];

        $result = $this->transport->send($phone, $messageBody, $mediaUrls);

        $this->linkKnownCustomer($conversation, $phone);

        $message = $this->recorder->recordOutboundSmsToConversation(
            conversation: $conversation,
            actor: $actor,
            body: $messageBody,
            providerMessageSid: $result->messageId,
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
            'provider_message_sid' => $result->messageId,
        ];
    }

    private function linkKnownCustomer(Conversation $conversation, string $phone): void
    {
        $normalizedPhone = PhoneNumber::normalize($phone);

        if ($normalizedPhone === null) {
            return;
        }

        $customer = Customer::query()->where('phone', $normalizedPhone)->first();

        if ($customer instanceof Customer) {
            $this->linker->link($conversation, $customer);
        }
    }
}
