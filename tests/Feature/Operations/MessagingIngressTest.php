<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use App\Ark\Operations\Messaging\MessagingHealth;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.account_sid', 'ACtestaccount');
    Storage::fake('local');
});

test('twilio messaging webhook creates conversation message for known customer', function () {
    config()->set('broadcasting.default', 'log');
    Event::fake([ConversationMessageReceived::class]);

    $customer = messagingCustomer('Jane', 'Driver', '7195551234');
    $vehicle = messagingVehicle($customer, 'Jeep', 'Wrangler');
    messagingRepairOrder($customer, $vehicle, RepairOrderStatus::WaitingApproval, 2201);

    $response = $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMincoming001',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'My Jeep is making this noise.',
        'NumMedia' => '0',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<Response>', false);

    $conversation = Conversation::query()->sole();

    expect($conversation->contact_surface)->toBe(ConversationContactSurface::Phone)
        ->and($conversation->contact_address)->toBe('7195551234');

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Inbound)
        ->and($message->body)->toBe('My Jeep is making this noise.')
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Customer)
        ->and($message->metadata['twilio_message_sid'])->toBe('SMincoming001')
        ->and($message->metadata['provider_message_id'])->toBe('SMincoming001');

    expect(ConversationLink::query()
        ->where('linkable_type', Customer::class)
        ->where('linkable_id', $customer->id)
        ->exists())->toBeTrue();

    expect(ConversationLink::query()
        ->where('linkable_type', RepairOrder::class)
        ->exists())->toBeTrue();

    Event::assertDispatched(ConversationMessageReceived::class, function (ConversationMessageReceived $event) use ($customer): bool {
        return $event->payload['customer_id'] === $customer->id
            && $event->payload['message']['body'] === 'My Jeep is making this noise.'
            && ($event->payload['hub_filter'] ?? '') === 'text'
            && ($event->payload['interrupt']['kind'] ?? '') === 'sms'
            && ($event->payload['interrupt']['state'] ?? '') === 'unread';
    });
});

test('inbound mms stores attachment on conversation message', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $customer = messagingCustomer('Photo', 'Sender', '3035550100');

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMmms0001',
        'From' => '+13035550100',
        'To' => '+17195559999',
        'Body' => '',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages/MM123/Media/ME456',
        'MediaContentType0' => 'image/jpeg',
    ])->assertOk();

    $message = ConversationMessage::query()->sole();

    expect($message->body)->toBe('(attachment)')
        ->and($message->attachments)->toHaveCount(1);

    $attachment = ConversationMessageAttachment::query()->sole();

    expect($attachment->content_type)->toBe('image/jpeg')
        ->and($attachment->storage_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($attachment->storage_path))->toBeTrue();
});

test('inbound mms audio attachment is stored with playable content type', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response('audio-bytes', 200, ['Content-Type' => 'audio/mp4']),
    ]);

    messagingCustomer('Voice', 'Memo', '3035550200');

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMmmsaudio001',
        'From' => '+13035550200',
        'To' => '+17195559999',
        'Body' => '',
        'NumMedia' => '1',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages/MM789/Media/ME999',
        'MediaContentType0' => 'audio/mp4',
    ])->assertOk();

    $attachment = ConversationMessageAttachment::query()->sole();

    expect($attachment->content_type)->toBe('audio/mp4')
        ->and($attachment->isAudio())->toBeTrue()
        ->and($attachment->storage_path)->toEndWith('.mp3')
        ->and(Storage::disk('local')->exists($attachment->storage_path))->toBeTrue();
});

test('messaging webhook is idempotent by provider message sid', function () {
    $customer = messagingCustomer('Repeat', 'Texter', '7195557777');

    $payload = [
        'MessageSid' => 'SMduplicate',
        'From' => '+17195557777',
        'To' => '+17195559999',
        'Body' => 'First send',
        'NumMedia' => '0',
    ];

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), $payload)->assertOk();
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), $payload)->assertOk();

    expect(ConversationMessage::query()->count())->toBe(1);
});

test('unknown texter still records conversation message on phone surface', function () {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMunknown01',
        'From' => '+15550100999',
        'To' => '+17195559999',
        'Body' => 'Is this Demo Auto Repair?',
        'NumMedia' => '0',
    ])->assertOk();

    $message = ConversationMessage::query()->sole();

    expect($message->body)->toBe('Is this Demo Auto Repair?')
        ->and($message->participant->customer_id)->toBeNull()
        ->and(Conversation::query()->sole()->contact_address)->toBe('5550100999');

    $lead = \App\Ark\Operations\Leads\Lead::query()->sole();

    expect($lead->source)->toBe(\App\Ark\Operations\Leads\LeadSource::Sms)
        ->and($lead->concern)->toBe('Is this Demo Auto Repair?');
});

test('messaging webhook rejects invalid signature when token configured', function () {
    config()->set('services.twilio.auth_token', 'configured-token');

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMbadauth01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'Should not land',
        'NumMedia' => '0',
    ])->assertUnauthorized();

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('advisor can send outbound sms through conversation authority', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMoutbound01',
            'status' => 'queued',
        ], 201),
    ]);

    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'AC-test');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = actingAsLearnCurrentAdvisor();
    $customer = messagingCustomer('Reply', 'Target', '7195554321');

    \App\Ark\Operations\Messaging\PhoneSmsCapability::query()->create([
        'normalized_phone' => \App\Ark\Operations\PhoneNumber::normalize('7195554321'),
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your estimate is ready for review.',
        ])
        ->assertOk()
        ->assertJsonPath('provider_message_sid', 'SMoutbound01');

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toBe('Your estimate is ready for review.')
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor)
        ->and($message->metadata['twilio_message_sid'])->toBe('SMoutbound01');
});

test('inbound sms appears on customer hub conversation rail', function () {
    ShopSettings::current()->update([
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_attention_gate_enabled' => false,
        ]),
    ]);

    $customer = messagingCustomer('Hub', 'Customer', '7195552468');

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMhub0001',
        'From' => '+17195552468',
        'To' => '+17195559999',
        'Body' => 'Here is a picture of the leak',
        'NumMedia' => '0',
    ])->assertOk();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Here is a picture of the leak')
        ->assertSee('Text')
        ->assertDontSee('SMS Thread');
});

test('messaging webhook updates health cache', function () {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMhealth001',
        'From' => '+17195551111',
        'To' => '+17195559999',
        'Body' => 'Ping',
        'NumMedia' => '0',
    ])->assertOk();

    expect(cache()->get(MessagingHealth::WEBHOOK_RECEIVED_CACHE_KEY))->not->toBeNull();
});

function messagingCustomer(string $first, string $last, string $phone): Customer
{
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'customer_type' => 'Retail',
    ]);
}

function messagingVehicle(Customer $customer, string $make, string $model): Vehicle
{
    return Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => $make,
        'model' => $model,
    ]);
}

function messagingRepairOrder(
    Customer $customer,
    Vehicle $vehicle,
    RepairOrderStatus $status,
    int $repairOrderNumber,
): RepairOrder {
    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => $repairOrderNumber,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Messaging ingress test',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Test concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
