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
use Illuminate\Support\Facades\Storage;
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
     * @param  list<UploadedFile>  $attachments
     * @return array{message: ?ConversationMessage, provider_message_sid: string}
     */
    public function execute(
        Conversation $conversation,
        User $actor,
        string $body,
        ?UploadedFile $attachment = null,
        ?string $idempotencyKey = null,
        array $attachments = [],
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

        $capability = PhoneSmsCapability::findByNormalizedPhone(PhoneNumber::normalize($phone));

        if ($capability !== null && ! $capability->sms_capable) {
            throw new RuntimeException($capability->blockReason() ?? 'This number cannot receive SMS.');
        }

        $files = [];
        if ($attachment instanceof UploadedFile) {
            $files[] = $attachment;
        }
        foreach ($attachments as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        $storedAttachments = $files === [] ? [] : $this->attachments->storeMany($files);
        $advisorBody = trim($body);

        if ($advisorBody === '' && $storedAttachments === []) {
            throw new RuntimeException('Enter a message or attach a file.');
        }

        $mediaUrls = array_map(
            static fn (array $stored): string => (string) $stored['public_url'],
            $storedAttachments,
        );
        $messageBody = $advisorBody === '' ? '(attachment)' : $advisorBody;

        $result = $this->transport->send(
            $phone,
            $advisorBody,
            $mediaUrls,
            filled($idempotencyKey) ? [['type' => 'idempotency_key', 'id' => 'conversation-send:'.$idempotencyKey]] : [],
        );

        // A repeated one-time key is answered with the first send's reference: that send is already in the conversation.
        $alreadyRecorded = filled($idempotencyKey) && filled($result->messageId)
            ? ConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('metadata->twilio_message_sid', $result->messageId)
                ->first()
            : null;

        if ($alreadyRecorded instanceof ConversationMessage) {
            foreach ($storedAttachments as $storedAttachment) {
                Storage::disk('local')->delete((string) $storedAttachment['storage_path']);
            }

            return [
                'message' => $alreadyRecorded,
                'provider_message_sid' => $result->messageId,
            ];
        }

        $this->linkKnownCustomer($conversation, $phone);

        $message = $this->recorder->recordOutboundSmsToConversation(
            conversation: $conversation,
            actor: $actor,
            body: $messageBody,
            providerMessageSid: $result->messageId,
        );

        foreach ($storedAttachments as $storedAttachment) {
            ConversationMessageAttachment::query()->create([
                'conversation_message_id' => $message->id,
                'content_type' => $storedAttachment['content_type'],
                'storage_path' => $storedAttachment['storage_path'],
                'byte_size' => $storedAttachment['byte_size'],
            ]);
        }

        if ($storedAttachments !== []) {
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
