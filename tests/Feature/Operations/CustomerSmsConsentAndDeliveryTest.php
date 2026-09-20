<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('stop keyword opts customer out without polluting conversation timeline', function () {
    $customer = smsConsentCustomer('Stop', 'Customer', '7195551234');
    smsConsentRepairOrder($customer);

    $result = processInboundSmsConsent('+17195551234', 'STOP', 'SMstop0001');

    expect($result['handled'])->toBeTrue()
        ->and($result['confirmation'])->toContain('unsubscribed');

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

    $result = processInboundSmsConsent('+17195555678', 'START', 'SMstart001');

    expect($result['handled'])->toBeTrue()
        ->and($result['confirmation'])->toContain('subscribed');

    expect(ConversationMessage::query()->count())->toBe(0);

    $customer->refresh();

    expect($customer->sms_consent_status)->toBe(CustomerSmsConsentStatus::Subscribed);
});

test('yes remains a normal reply when customer is subscribed', function () {
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    smsConsentCustomer('Yes', 'Customer', '7195554321');

    ingestInboundSms('7195554321', 'YES', 'SMyes00001');

    $message = ConversationMessage::query()->sole();

    expect($message->body)->toBe('YES');
});

test('outbound sms is blocked when customer opted out', function () {
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
});

test('outbound sms records delivery status from transport result', function () {
    bindFakeOutboundSms('SMcallback01', 'queued');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = actingAsLearnCurrentAdvisor();
    $customer = smsConsentCustomer('Callback', 'Target', '7195552468');

    seedMobileSmsCapability('7195552468');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your vehicle is ready.',
        ])
        ->assertOk();

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('queued');
});

test('delivery status action records failed delivery facts on customer', function () {
    $customer = smsConsentCustomer('Failed', 'Delivery', '7195551357');

    recordSmsDeliveryStatus($customer, 'SMfailed001', 'undelivered', '30007');

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('undelivered')
        ->and($customer->last_sms_failed_at)->not->toBeNull()
        ->and($customer->last_sms_error_code)->toBe('30007');
});

test('delivery status action records delivered facts and clears error code', function () {
    $customer = smsConsentCustomer('Delivered', 'Customer', '7195552460');
    $customer->forceFill([
        'last_sms_delivery_status' => 'undelivered',
        'last_sms_error_code' => '30007',
        'last_sms_failed_at' => now()->subDay(),
    ])->save();

    recordSmsDeliveryStatus($customer, 'SMdelivered1', 'delivered');

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('delivered')
        ->and($customer->last_sms_delivered_at)->not->toBeNull()
        ->and($customer->last_sms_error_code)->toBeNull();
});

test('opted out customer sees sms blocked notice on customer hub', function () {
    bindFakeOutboundSms();

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
    bindFakeOutboundSms();

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
