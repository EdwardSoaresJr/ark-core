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
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
});

function fakeTwilioLookup(string $type = 'mobile', bool $valid = true): void
{
    Http::fake([
        'lookups.twilio.com/*' => Http::response([
            'calling_country_code' => '1',
            'country_code' => 'US',
            'phone_number' => '+17195551234',
            'national_format' => '(719) 555-1234',
            'valid' => $valid,
            'validation_errors' => $valid ? null : ['TOO_SHORT'],
            'line_type_intelligence' => [
                'error_code' => null,
                'mobile_country_code' => '310',
                'mobile_network_code' => '260',
                'carrier_name' => 'T-Mobile USA',
                'type' => $type,
            ],
        ], 200),
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMlookupsend01',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();
}

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

test('resolve action persists twilio line type lookup', function () {
    fakeTwilioLookup('landline');

    $capability = app(ResolvePhoneSmsCapabilityAction::class)->execute('7195551234');

    expect($capability)->not->toBeNull()
        ->and($capability->normalized_phone)->toBe('7195551234')
        ->and($capability->line_type)->toBe('landline')
        ->and($capability->sms_capable)->toBeFalse()
        ->and($capability->reason)->toContain('Landline')
        ->and(PhoneSmsCapability::query()->count())->toBe(1);
});

test('resolve action reuses fresh capability without another lookup', function () {
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

    Http::fake();

    $capability = app(ResolvePhoneSmsCapabilityAction::class)->execute('7195551234');

    expect($capability?->carrier_name)->toBe('Cached');
    Http::assertNothingSent();
});

test('eligibility blocks send when stored capability is not sms capable', function () {
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

test('outbound send looks up and refuses landline before twilio messages api', function () {
    fakeTwilioLookup('landline');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Land',
        'last_name' => 'Line',
        'phone' => '7195551234',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    expect(fn () => app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Hello',
    ))->toThrow(RuntimeException::class, 'cannot receive SMS');

    expect(PhoneSmsCapability::query()->where('normalized_phone', '7195551234')->value('sms_capable'))->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lookups.twilio.com'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'Messages.json'));
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
