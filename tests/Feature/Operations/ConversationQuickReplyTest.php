<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Broadcasting\BroadcastException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
            Storage::fake('local');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
});

test('quick reply returns rendered conversation html', function () {
    bindFakeOutboundSms('SMquickreply01');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = quickReplyCustomer('Lane', 'Customer', '7195551212');

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Yes, anytime after 3 PM.',
        ]);

    $response->assertOk()
        ->assertJsonPath('message.body', 'Yes, anytime after 3 PM.')
        ->assertJsonStructure(['html', 'message_id']);

    expect(str_contains((string) $response->json('html'), 'Yes, anytime after 3 PM.'))->toBeTrue();
});

test('quick reply succeeds when realtime broadcast fails', function () {
    config()->set('broadcasting.default', 'reverb');

    bindFakeOutboundSms('SMquickreply05');

    $broadcastException = new BroadcastException('Pusher error: Internal server error.');

    Broadcast::shouldReceive('event')->andThrow($broadcastException);
    Broadcast::shouldReceive('queue')->andThrow($broadcastException);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = quickReplyCustomer('Broadcast', 'Retry', '7195559090');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Aight bet homie.',
        ])
        ->assertOk()
        ->assertJsonPath('message.body', 'Aight bet homie.')
        ->assertJsonStructure(['html', 'message_id']);

    expect(ConversationMessage::query()->sole()->body)->toBe('Aight bet homie.');
});

test('quick reply sends mms attachment through conversation authority', function () {
    bindFakeOutboundSms('SMquickreply02');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = quickReplyCustomer('Media', 'Sender', '7195553434');

    $this->actingAs($advisor)
        ->post(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Here is the photo.',
            'attachment' => UploadedFile::fake()->image('leak.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('message.body', 'Here is the photo.');

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor)
        ->and($message->attachments)->toHaveCount(1);

    $attachment = ConversationMessageAttachment::query()->sole();

    expect($attachment->content_type)->toBe('image/jpeg')
        ->and(Storage::disk('local')->exists($attachment->storage_path))->toBeTrue();
});

test('quick reply links outbound message to repair order context', function () {
    bindFakeOutboundSms('SMquickreply03');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = quickReplyCustomer('Fleet', 'Owner', '3035550100');
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'Transit',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 3301,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Transit turnaround',
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'The Transit will be ready tomorrow morning.',
            'repair_order_id' => $repairOrder->repair_order_id,
        ])
        ->assertOk();

    expect(ConversationMessage::query()->sole()->metadata['repair_order_id'])->toBe($repairOrder->id);
});

test('customer hub shows text customer affordance when no conversation history', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = quickReplyCustomer('Hub', 'Starter', '7195555656');

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Attach Picture?')
        ->assertSee('First text or MMS')
        ->assertDontSee('SMS Inbox');
});

test('customer hub shows reply affordance when conversation history exists', function () {
    bindFakeOutboundSms('SMquickreply04');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = quickReplyCustomer('Hub', 'Reply', '7195555657');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'We can look at it tomorrow morning.',
        ])
        ->assertOk();

    $this->actingAs($advisor)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Attach Picture?')
        ->assertSee('Message or MMS')
        ->assertSee('We can look at it tomorrow morning.');
});

function quickReplyCustomer(string $first, string $last, string $phone): Customer
{
    $customer = Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'customer_type' => 'Retail',
    ]);

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => PhoneNumber::normalize($phone)],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
            'raw_payload' => ['source' => 'test'],
        ],
    );

    return $customer;
}
