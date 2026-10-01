<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
});

test('authorized ops user can view a valid conversation attachment', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$conversation, $message, $attachment] = conversationAttachmentFixture('attachments/photo.jpg');

    $response = $this->actingAs($advisor)->get(
        conversationAttachmentRoute($conversation, $message, $attachment),
    );

    $response->assertOk();
});

test('guest cannot view conversation attachments', function () {
    [$conversation, $message, $attachment] = conversationAttachmentFixture('attachments/photo.jpg');

    $this->get(conversationAttachmentRoute($conversation, $message, $attachment))
        ->assertRedirect(route('login'));
});

test('ops user cannot view attachment through wrong message context', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$conversation, $message, $attachment] = conversationAttachmentFixture('attachments/photo.jpg');

    $otherMessage = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $message->conversation_participant_id,
        'channel' => $message->channel,
        'direction' => $message->direction,
        'body' => 'Other message',
        'occurred_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->get(conversationAttachmentRoute($conversation, $otherMessage, $attachment))
        ->assertNotFound();
});

test('ops user cannot view attachment through wrong conversation context', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$conversation, $message, $attachment] = conversationAttachmentFixture('attachments/photo.jpg');

    $otherConversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195559090',
        'status' => ConversationStatus::Open,
    ]);

    $this->actingAs($advisor)
        ->get(conversationAttachmentRoute($otherConversation, $message, $attachment))
        ->assertNotFound();
});

test('missing storage path returns 404 for conversation attachments', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$conversation, $message, $attachment] = conversationAttachmentFixture(null);

    $this->actingAs($advisor)
        ->get(conversationAttachmentRoute($conversation, $message, $attachment))
        ->assertNotFound();
});

/**
 * @return array{0: Conversation, 1: ConversationMessage, 2: ConversationMessageAttachment}
 */
function conversationAttachmentFixture(?string $storagePath): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Attachment',
        'last_name' => 'Customer',
        'phone' => '7195557070',
        'customer_type' => 'Retail',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $customer->phone,
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    $message = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => \App\Ark\Operations\Communications\OperationalCommunicationChannel::Sms,
        'direction' => \App\Ark\Operations\Communications\OperationalCommunicationDirection::Inbound,
        'body' => 'Photo attached',
        'occurred_at' => now(),
    ]);

    if ($storagePath !== null) {
        Storage::disk('local')->put($storagePath, 'image-bytes');
    }

    $attachment = ConversationMessageAttachment::query()->create([
        'conversation_message_id' => $message->id,
        'content_type' => 'image/jpeg',
        'storage_path' => $storagePath,
        'byte_size' => $storagePath !== null ? strlen('image-bytes') : null,
    ]);

    return [$conversation, $message, $attachment];
}

function conversationAttachmentRoute(
    Conversation $conversation,
    ConversationMessage $message,
    ConversationMessageAttachment $attachment,
): string {
    return route('operations.conversation-attachments.show', [
        'conversation' => $conversation,
        'message' => $message,
        'attachment' => $attachment,
    ]);
}
