<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        });

test('stop keyword opts customer out without polluting conversation timeline', function () {
    $customer = smsConsentCustomer('Stop', 'Customer', '7195551234');
    smsConsentRepairOrder($customer);

    $response = $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMstop0001',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'STOP',
        'NumMedia' => '0',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
        ->assertSee('<Message>', false)
        ->assertSee('unsubscribed', false);

    expect(ConversationMessage::query()->count())->toBe(0);

    $customer->refresh();

    expect($customer->sms_consent_status)->toBe(CustomerSmsConsentStatus::OptedOut)
        ->and($customer->sms_consent_at)->not->toBeNull();

    expect(CommunicationEvent::query()
        ->where('event_type', OperationalCommunicationType::SmsOptOut)
        ->exists())->toBeTrue();
});

test('start keyword re-enables sms for opted out customer without timeline pollution', function () {
    $customer = smsConsentCustomer('Start', 'Customer', '7195555678', CustomerSmsConsentStatus::OptedOut);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMstart001',
        'From' => '+17195555678',
        'To' => '+17195559999',
        'Body' => 'START',
        'NumMedia' => '0',
    ])->assertOk()
        ->assertSee('subscribed', false);

    expect(ConversationMessage::query()->count())->toBe(0);

    $customer->refresh();

    expect($customer->sms_consent_status)->toBe(CustomerSmsConsentStatus::Subscribed);
});

test('yes remains a normal reply when customer is subscribed', function () {
    smsConsentCustomer('Yes', 'Customer', '7195554321');

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMyes00001',
        'From' => '+17195554321',
        'To' => '+17195559999',
        'Body' => 'YES',
        'NumMedia' => '0',
    ])->assertOk();

    $message = ConversationMessage::query()->sole();

    expect($message->body)->toBe('YES');
});

test('outbound sms is blocked when customer opted out', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMblocked01',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = actingAsLearnCurrentAdvisor();
    $customer = smsConsentCustomer('Blocked', 'Texter', '7195558888', CustomerSmsConsentStatus::OptedOut);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Following up on your estimate.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Customer has opted out of text messages. Call the customer or use email.');

    expect(ConversationMessage::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('outbound sms registers twilio status callback url', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMcallback01',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = actingAsLearnCurrentAdvisor();
    $customer = smsConsentCustomer('Callback', 'Target', '7195552468');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your vehicle is ready.',
        ])
        ->assertOk();

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('queued');
});

test('delivery status webhook records failed delivery facts on customer', function () {
    $customer = smsConsentCustomer('Failed', 'Delivery', '7195551357');

    $this->post(route('webhooks.communications.twilio.messaging.status'), [
        'MessageSid' => 'SMfailed001',
        'MessageStatus' => 'undelivered',
        'To' => '+17195551357',
        'From' => '+17195559999',
        'ErrorCode' => '30007',
    ])->assertNoContent();

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('undelivered')
        ->and($customer->last_sms_failed_at)->not->toBeNull()
        ->and($customer->last_sms_error_code)->toBe('30007');
});

test('delivery status webhook records delivered facts and clears error code', function () {
    $customer = smsConsentCustomer('Delivered', 'Customer', '7195552460');
    $customer->forceFill([
        'last_sms_delivery_status' => 'undelivered',
        'last_sms_error_code' => '30007',
        'last_sms_failed_at' => now()->subDay(),
    ])->save();

    $this->post(route('webhooks.communications.twilio.messaging.status'), [
        'MessageSid' => 'SMdelivered1',
        'MessageStatus' => 'delivered',
        'To' => '+17195552460',
        'From' => '+17195559999',
    ])->assertNoContent();

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('delivered')
        ->and($customer->last_sms_delivered_at)->not->toBeNull()
        ->and($customer->last_sms_error_code)->toBeNull();
});

test('opted out customer sees sms blocked notice on customer hub', function () {
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $customer = smsConsentCustomer('Hub', 'OptOut', '7195559090', CustomerSmsConsentStatus::OptedOut);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer).'?compose=text#customer-communication')
        ->assertOk()
        ->assertSee('opted out of text messages', false);
});

test('recent delivery failure shows warning on customer hub', function () {
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $customer = smsConsentCustomer('Hub', 'Warning', '7195559191');
    $customer->forceFill([
        'last_sms_delivery_status' => 'failed',
        'last_sms_failed_at' => now()->subDay(),
        'last_sms_error_code' => '30003',
    ])->save();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer).'?compose=text#customer-communication')
        ->assertOk()
        ->assertSee('Recent text delivery failed', false)
        ->assertSee('30003', false);
});
