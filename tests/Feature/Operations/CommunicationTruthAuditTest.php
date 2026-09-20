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

test('duplicate stop keyword keeps consent state and records one opt out event', function () {
    $customer = smsConsentCustomer('Dup', 'Stop', '7195551414');
    $repairOrder = smsConsentRepairOrder($customer);

    foreach (['SMstop1', 'SMstop2'] as $sid) {
        processInboundSmsConsent('+17195551414', 'STOP', $sid);
    }

    $customer->refresh();

    expect($customer->sms_consent_status)->toBe(CustomerSmsConsentStatus::OptedOut)
        ->and(CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::SmsOptOut)
            ->count())->toBe(1);
});

test('stop start stop consent recovery records two opt out events and one opt in', function () {
    $customer = smsConsentCustomer('Osc', 'Consent', '7195551515');
    $repairOrder = smsConsentRepairOrder($customer);

    processInboundSmsConsent('+17195551515', 'STOP', 'SMosc1');
    processInboundSmsConsent('+17195551515', 'START', 'SMosc2');
    processInboundSmsConsent('+17195551515', 'STOP', 'SMosc3');

    $customer->refresh();

    expect($customer->sms_consent_status)->toBe(CustomerSmsConsentStatus::OptedOut)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::SmsOptOut)->count())->toBe(2)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::SmsOptIn)->count())->toBe(1)
        ->and(CommunicationEvent::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(3);
});

test('all opt out keywords opt customer out', function (string $keyword, string $phone) {
    $customer = smsConsentCustomer('Kw', 'OptOut', $phone);
    smsConsentRepairOrder($customer);

    processInboundSmsConsent('+1'.$phone, strtoupper($keyword), 'SMkw'.$keyword);

    expect($customer->fresh()->sms_consent_status)->toBe(CustomerSmsConsentStatus::OptedOut);
})->with([
    ['stop', '7195552201'],
    ['stopall', '7195552202'],
    ['unsubscribe', '7195552203'],
    ['cancel', '7195552204'],
    ['end', '7195552205'],
    ['quit', '7195552206'],
]);

test('queued failed delivered recovery updates customer state and records both delivery facts', function () {
    bindFakeOutboundSms('SMrecovery1');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = actingAsLearnCurrentAdvisor();
    $customer = smsConsentCustomer('Rec', 'Overy', '7195551616');
    $repairOrder = smsConsentRepairOrder($customer);

    seedMobileSmsCapability('7195551616');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your vehicle is ready.',
        ])
        ->assertOk();

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('queued');

    recordSmsDeliveryStatus($customer, 'SMrecovery1', 'failed', '30003');

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('failed')
        ->and($customer->last_sms_failed_at)->not->toBeNull()
        ->and($customer->last_sms_error_code)->toBe('30003');

    recordSmsDeliveryStatus($customer, 'SMrecovery1', 'delivered');

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('delivered')
        ->and($customer->last_sms_delivered_at)->not->toBeNull()
        ->and($customer->last_sms_error_code)->toBeNull()
        ->and(CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::SmsDeliveryFailed)
            ->count())->toBe(1)
        ->and(CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::SmsDelivered)
            ->count())->toBe(1);

    $message = ConversationMessage::query()
        ->where('metadata->twilio_message_sid', 'SMrecovery1')
        ->sole();

    expect($message->id)->not->toBeNull();
});

test('duplicate delivered callback is idempotent for state and delivery fact', function () {
    $customer = smsConsentCustomer('Dup', 'Delivered', '7195551717');
    $repairOrder = smsConsentRepairOrder($customer);

    recordSmsDeliveryStatus($customer, 'SMdupdel1', 'delivered');

    $customer->refresh();
    $firstDeliveredAt = $customer->last_sms_delivered_at;

    recordSmsDeliveryStatus($customer, 'SMdupdel1', 'delivered');

    $customer->refresh();

    expect($customer->last_sms_delivery_status)->toBe('delivered')
        ->and($customer->last_sms_delivered_at?->eq($firstDeliveredAt))->toBeTrue()
        ->and(CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::SmsDelivered)
            ->count())->toBe(1);
});

test('failed delivery without outbound message still records delivery fact on latest repair order', function () {
    $customer = smsConsentCustomer('No', 'Message', '7195551818');
    $repairOrder = smsConsentRepairOrder($customer);

    recordSmsDeliveryStatus($customer, 'SMorphan1', 'undelivered', '30007');

    expect(CommunicationEvent::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('event_type', OperationalCommunicationType::SmsDeliveryFailed)
        ->exists())->toBeTrue();
});
