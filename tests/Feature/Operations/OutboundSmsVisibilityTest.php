<?php

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerHubCommsTimeline;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

function smsCapableCustomer(Customer $customer, string $phone): Customer
{
    $customer->forceFill(['phone' => $phone])->save();

    PhoneSmsCapability::query()->create([
        'normalized_phone' => PhoneNumber::normalize($phone),
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    return $customer->refresh();
}

test('outbound sms appears on customer hub timeline after send', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    bindFakeOutboundSms();

        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Outbound Hub Customer');
    $customer = smsCapableCustomer($repairOrder->customer, '7195551000');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'We can get you in tomorrow morning.',
            'repair_order_id' => $repairOrder->repair_order_id,
        ])
        ->assertOk()
        ->assertJsonPath('provider_message_sid', 'SMoutboundhub');

    $message = ConversationMessage::query()->sole();

    expect($message->direction)->toBe(OperationalCommunicationDirection::Outbound);

    expect(ConversationLink::query()
        ->where('conversation_id', $message->conversation_id)
        ->where('linkable_type', $customer->getMorphClass())
        ->where('linkable_id', $customer->id)
        ->exists())->toBeTrue();

    $timeline = app(CustomerHubCommsTimeline::class)->buildForCustomer(
        $customer,
        PhoneNumber::normalize($customer->phone),
        50,
    );

    expect($timeline->contains(fn ($row) => str_contains((string) $row->body, 'We can get you in tomorrow morning.')))
        ->toBeTrue();

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('We can get you in tomorrow morning.');
});

test('mobile conversation send records on the viewed conversation thread', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMmobilethread',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Mobile Thread Customer');
    $customer = smsCapableCustomer($repairOrder->customer, '7195552000');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'First message to seed conversation.',
        ])
        ->assertOk();

    $conversationId = ConversationMessage::query()->value('conversation_id');

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->postJson(route('api.mobile.conversations.messages.store', ['conversation' => $conversationId]), [
            'body' => 'Reply from mobile thread.',
            'repair_order_id' => $repairOrder->repair_order_id,
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $messages = ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->orderBy('id')
        ->pluck('body');

    expect($messages->all())->toBe([
        'First message to seed conversation.',
        'Reply from mobile thread.',
    ]);
});
