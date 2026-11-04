<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\PhoneSmsCapabilityClassifier;
use App\Ark\Operations\Messaging\RecordCustomerSmsDeliveryStatusAction;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\Messaging\SendOutboundContactMessageAction;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
});

test('classifier marks landlines as not sms capable with reason', function () {
    $result = app(PhoneSmsCapabilityClassifier::class)->classify(
        valid: true,
        lineType: 'landline',
        carrierName: 'CenturyLink',
    );

    expect($result['sms_capable'])->toBeFalse()
        ->and($result['reason'])->toContain('Landline')
        ->and($result['reason'])->toContain('cannot receive SMS');
});

test('resolve action returns null when transport is not configured and no cached capability', function () {
    $capability = app(ResolvePhoneSmsCapabilityAction::class)->execute('7195551234');

    expect($capability)->toBeNull()
        ->and(PhoneSmsCapability::query()->count())->toBe(0);
});

test('resolve action reuses fresh capability without refresh', function () {
    PhoneSmsCapability::query()->create([
        'normalized_phone' => '7195551234',
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Cached',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    $capability = app(ResolvePhoneSmsCapabilityAction::class)->execute('7195551234');

    expect($capability?->carrier_name)->toBe('Cached');
});

test('inbound sms marks phone as sms capable', function () {
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    ingestInboundSms('7195551234', 'Hello from customer', 'SMinbound001');

    $capability = PhoneSmsCapability::findByNormalizedPhone('7195551234');

    expect($capability)->not->toBeNull()
        ->and($capability->sms_capable)->toBeTrue()
        ->and($capability->line_type)->toBe('mobile');
});

test('eligibility blocks send when stored capability is not sms capable', function () {
    bindFakeOutboundSms();

    $customer = Customer::query()->create([
        'first_name' => 'Land',
        'last_name' => 'Line',
        'phone' => '7195551234',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    PhoneSmsCapability::query()->create([
        'normalized_phone' => '7195551234',
        'valid' => true,
        'line_type' => 'landline',
        'carrier_name' => 'CenturyLink',
        'sms_capable' => false,
        'reason' => 'Landline (CenturyLink) - cannot receive SMS.',
        'checked_at' => now(),
    ]);

    $eligibility = CustomerSmsSendEligibility::for($customer, ShopIntegrationCredentials::forCurrentShop());

    expect($eligibility->canSend())->toBeFalse()
        ->and($eligibility->blockReason())->toContain('cannot receive SMS');
});

test('outbound send refuses landline when capability is stored', function () {
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Land',
        'last_name' => 'Line',
        'phone' => '7195551234',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    PhoneSmsCapability::query()->create([
        'normalized_phone' => '7195551234',
        'valid' => true,
        'line_type' => 'landline',
        'carrier_name' => 'CenturyLink',
        'sms_capable' => false,
        'reason' => 'Landline (CenturyLink) - cannot receive SMS.',
        'checked_at' => now(),
    ]);

    expect(fn () => app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Hello',
    ))->toThrow(RuntimeException::class, 'cannot receive SMS');
});

test('delivery failure for landline error marks phone not sms capable', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Fail',
        'last_name' => 'Land',
        'phone' => '7195551234',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute(
        $customer,
        'SMfailland01',
        'undelivered',
        '21614',
    );

    $capability = PhoneSmsCapability::findByNormalizedPhone('7195551234');

    expect($capability)->not->toBeNull()
        ->and($capability->sms_capable)->toBeFalse()
        ->and($capability->reason)->toContain('21614');
});

function capabilityCustomer(string $phone, CustomerSmsConsentStatus $consent = CustomerSmsConsentStatus::Subscribed): Customer
{
    return Customer::query()->create([
        'first_name' => 'Two',
        'last_name' => 'Numbers',
        'phone' => $phone,
        'sms_consent_status' => $consent,
    ]);
}

function capabilityConversation(string $phone, ?Customer $customer = null): Conversation
{
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $phone,
        'status' => ConversationStatus::Open,
    ]);

    if ($customer !== null) {
        app(ConversationLinker::class)->link($conversation, $customer);
    }

    return $conversation;
}

function storedCapability(string $phone, bool $capable): void
{
    PhoneSmsCapability::query()->create([
        'normalized_phone' => $phone,
        'valid' => true,
        'line_type' => $capable ? 'mobile' : 'landline',
        'carrier_name' => 'Test',
        'sms_capable' => $capable,
        'reason' => $capable ? null : 'Landline (Test) - cannot receive SMS.',
        'checked_at' => now(),
    ]);
}

test('a failed text to a secondary number marks that number and leaves the main phone and customer alone', function () {
    $customer = capabilityCustomer('7195552001');

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMsecond01', 'undelivered', '30006', '+17195552002');

    expect(PhoneSmsCapability::findByNormalizedPhone('7195552002')?->sms_capable)->toBeFalse()
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195552001'))->toBeNull()
        ->and($customer->fresh()->last_sms_delivery_status)->toBeNull()
        ->and($customer->fresh()->last_sms_error_code)->toBeNull();
});

test('without a callback number the failed text is matched to its own conversation number', function () {
    $customer = capabilityCustomer('7195552011');
    $conversation = capabilityConversation('7195552012', $customer);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'participant_type' => ConversationParticipantType::Customer,
            'customer_id' => $customer->id,
        ])->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'Your car is ready.',
        'occurred_at' => now(),
        'metadata' => ['twilio_message_sid' => 'SMsecond02'],
    ]);

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMsecond02', 'failed', '21614');

    expect(PhoneSmsCapability::findByNormalizedPhone('7195552012')?->sms_capable)->toBeFalse()
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195552011'))->toBeNull()
        ->and($customer->fresh()->last_sms_delivery_status)->toBeNull();
});

test('a failed text to the main phone still records the customer delivery result', function () {
    $customer = capabilityCustomer('7195552021');

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMmain01', 'undelivered', '30006', '+17195552021');

    expect(PhoneSmsCapability::findByNormalizedPhone('7195552021')?->sms_capable)->toBeFalse()
        ->and($customer->fresh()->last_sms_delivery_status)->toBe('undelivered')
        ->and($customer->fresh()->last_sms_error_code)->toBe('30006');
});

test('changing the main phone clears the old number delivery result but keeps that number marked', function () {
    $customer = capabilityCustomer('7195552031');

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMchange01', 'undelivered', '30006', '7195552031');
    $customer->fresh()->update(['phone' => '(719) 555-2032']);

    $customer = $customer->fresh();
    $eligibility = CustomerSmsSendEligibility::for($customer, ShopIntegrationCredentials::forCurrentShop());

    expect($customer->last_sms_delivery_status)->toBeNull()
        ->and($customer->last_sms_failed_at)->toBeNull()
        ->and($customer->last_sms_error_code)->toBeNull()
        ->and($eligibility->capability())->toBeNull()
        ->and($eligibility->deliveryWarning())->toBeNull()
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195552031')?->sms_capable)->toBeFalse();
});

test('a failed text to a number with no customer marks that number', function () {
    app(RecordCustomerSmsDeliveryStatusAction::class)->recordForNumber('+17195552041', 'undelivered', '30006');
    app(RecordCustomerSmsDeliveryStatusAction::class)->recordForNumber('+17195552042', 'failed', '30003');

    expect(PhoneSmsCapability::findByNormalizedPhone('7195552041')?->sms_capable)->toBeFalse()
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195552042'))->toBeNull();
});

test('sending checks the number the text goes to, not the main phone', function () {
    config(['services.twilio.account_sid' => 'ACsynthetic', 'services.twilio.auth_token' => 'synthetic-token']);
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $landlineMain = capabilityCustomer('7195552051');
    storedCapability('7195552051', false);
    storedCapability('7195552052', true);

    app(SendOutboundMessageAction::class)->execute(customer: $landlineMain, actor: $advisor, body: 'To the mobile', toPhone: '7195552052');

    expect($transport->sent)->toHaveCount(1)
        ->and($transport->sent[0]['to'])->toBe('7195552052')
        ->and(fn () => app(SendOutboundMessageAction::class)->execute(customer: $landlineMain, actor: $advisor, body: 'To the landline'))
        ->toThrow(RuntimeException::class, 'cannot receive SMS');

    $mobileMain = capabilityCustomer('7195552053');
    storedCapability('7195552054', false);

    expect(fn () => app(SendOutboundMessageAction::class)->execute(customer: $mobileMain, actor: $advisor, body: 'To the landline', toPhone: '7195552054'))
        ->toThrow(RuntimeException::class, 'cannot receive SMS');

    $optedOut = capabilityCustomer('7195552055', CustomerSmsConsentStatus::OptedOut);

    expect(fn () => app(SendOutboundMessageAction::class)->execute(customer: $optedOut, actor: $advisor, body: 'Opted out', toPhone: '7195552056'))
        ->toThrow(RuntimeException::class, 'opted out')
        ->and($transport->sent)->toHaveCount(1);
});

test('a reply in a conversation with no customer refuses a number known to be unable to text', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    storedCapability('7195552061', false);

    expect(fn () => app(SendOutboundContactMessageAction::class)->execute(capabilityConversation('7195552061'), $advisor, 'Hello'))
        ->toThrow(RuntimeException::class, 'cannot receive SMS');

    app(SendOutboundContactMessageAction::class)->execute(capabilityConversation('7195552062'), $advisor, 'Hello');

    expect($transport->sent)->toHaveCount(1)
        ->and($transport->sent[0]['to'])->toBe('7195552062');
});
