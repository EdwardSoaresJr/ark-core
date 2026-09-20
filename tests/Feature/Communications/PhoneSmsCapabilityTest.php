<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\PhoneSmsCapabilityClassifier;
use App\Ark\Operations\Messaging\RecordCustomerSmsDeliveryStatusAction;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
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
        'reason' => 'Landline (CenturyLink) — cannot receive SMS.',
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
        'reason' => 'Landline (CenturyLink) — cannot receive SMS.',
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
