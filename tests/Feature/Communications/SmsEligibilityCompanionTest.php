<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceSignalPresenter;
use App\Ark\Operations\Communications\CommunicationsWorkspaceSignals;
use App\Ark\Operations\Communications\CoreCommunicationsWorkspaceSignals;
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
use App\Ark\Operations\Messaging\SmsDeliveryFailureClass;
use App\Ark\Operations\Messaging\SmsDeliveryFailureClassifier;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    ShopSettings::current()->update([
        'shop_name' => 'Companion Auto',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
});

function companionBindSmsTransport(): void
{
    config([
        'services.twilio.account_sid' => 'ACsyntheticcompanion',
        'services.twilio.auth_token' => 'synthetic-companion-token',
    ]);
    bindFakeOutboundSms('SMcompanion000000000000000000000001');
}

function companionCapability(string $phone, bool $capable, string $lineType = 'mobile'): void
{
    PhoneSmsCapability::query()->create([
        'normalized_phone' => $phone,
        'valid' => true,
        'line_type' => $lineType,
        'carrier_name' => 'Test',
        'sms_capable' => $capable,
        'reason' => $capable ? null : 'Landline (Test) - cannot receive SMS.',
        'checked_at' => now(),
    ]);
}

test('presenter never invites retry for STOP or landline delivery failures', function () {
    $stop = CommunicationsWorkspaceSignalPresenter::delivery([
        'status' => 'undelivered',
        'error_code' => '21610',
    ]);
    $landline = CommunicationsWorkspaceSignalPresenter::delivery([
        'status' => 'failed',
        'error_code' => '30006',
    ]);
    $temporary = CommunicationsWorkspaceSignalPresenter::delivery([
        'status' => 'undelivered',
        'error_code' => '30003',
    ]);

    expect($stop['allows_retry'])->toBeFalse()
        ->and($stop['detail'])->not->toContain('send it again')
        ->and($stop['detail'])->toContain('STOP')
        ->and($landline['allows_retry'])->toBeFalse()
        ->and($landline['detail'])->not->toContain('send it again')
        ->and($landline['detail'])->toContain('landline')
        ->and($temporary['allows_retry'])->toBeTrue()
        ->and($temporary['detail'])->toContain('try again later');
});

test('presenter shows Texting unavailable for landline and service-disabled permissions', function () {
    $landline = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unavailable',
        'unavailable_reason' => 'landline',
        'source' => 'This number cannot receive texts.',
    ]);
    $service = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unavailable',
        'unavailable_reason' => 'service_disabled',
        'source' => 'Shop texting is not available',
    ]);
    $legacyUnknown = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unknown',
        'source' => 'Texting unavailable for this number',
        'notes' => ['Landline (Test) - cannot receive texts.'],
    ]);

    expect($landline['texting'])->toBe('unavailable')
        ->and($landline['label'])->toContain('landline')
        ->and($landline['label'])->not->toBe('Texting allowed')
        ->and($service['label'])->toBe('Texting unavailable')
        ->and($legacyUnknown['texting'])->toBe('unavailable')
        ->and($legacyUnknown['label'])->toContain('Texting unavailable');
});

test('core signals mark destination landline unavailable without blaming the primary phone', function () {
    companionBindSmsTransport();
    $customer = Customer::query()->create([
        'first_name' => 'Multi',
        'last_name' => 'Number',
        'phone' => '7195553001',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    companionCapability('7195553001', true);
    companionCapability('7195553002', false, 'landline');

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553002',
        'status' => ConversationStatus::Open,
    ]);
    app(ConversationLinker::class)->link($conversation, $customer);

    $permission = app(CoreCommunicationsWorkspaceSignals::class)->contactPermission($conversation, $customer);

    expect($permission['texting'])->toBe('unavailable')
        ->and($permission['unavailable_reason'])->toBe('landline')
        ->and(CustomerSmsSendEligibility::for($customer, ShopIntegrationCredentials::forCurrentShop(), '7195553001')->canSend())->toBeTrue()
        ->and(CustomerSmsSendEligibility::for($customer, ShopIntegrationCredentials::forCurrentShop(), '7195553002')->canSend())->toBeFalse();
});

test('changed primary phone eligibility uses the new number while the old landline stays blocked', function () {
    companionBindSmsTransport();
    $customer = Customer::query()->create([
        'first_name' => 'Changed',
        'last_name' => 'Primary',
        'phone' => '7195553011',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    companionCapability('7195553011', false, 'landline');
    $customer->update(['phone' => '7195553012']);
    companionCapability('7195553012', true);

    expect(CustomerSmsSendEligibility::for($customer->fresh(), ShopIntegrationCredentials::forCurrentShop())->canSend())->toBeTrue()
        ->and(CustomerSmsSendEligibility::for($customer->fresh(), ShopIntegrationCredentials::forCurrentShop(), '7195553011')->canSend())->toBeFalse();
});

test('STOP consent still blocks every destination while temporary failure warnings may invite retry', function () {
    companionBindSmsTransport();
    $optedOut = Customer::query()->create([
        'first_name' => 'Stop',
        'last_name' => 'Customer',
        'phone' => '7195553021',
        'sms_consent_status' => CustomerSmsConsentStatus::OptedOut,
    ]);
    companionCapability('7195553022', true);

    $temporary = Customer::query()->create([
        'first_name' => 'Temp',
        'last_name' => 'Fail',
        'phone' => '7195553023',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
        'last_sms_delivery_status' => 'undelivered',
        'last_sms_error_code' => '30003',
        'last_sms_failed_at' => now(),
    ]);
    $permanent = Customer::query()->create([
        'first_name' => 'Perm',
        'last_name' => 'Fail',
        'phone' => '7195553024',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
        'last_sms_delivery_status' => 'undelivered',
        'last_sms_error_code' => '21610',
        'last_sms_failed_at' => now(),
    ]);

    expect(CustomerSmsSendEligibility::for($optedOut, ShopIntegrationCredentials::forCurrentShop(), '7195553022')->canSend())->toBeFalse()
        ->and(CustomerSmsSendEligibility::for($temporary, ShopIntegrationCredentials::forCurrentShop())->deliveryWarning())->toContain('before texting again')
        ->and(CustomerSmsSendEligibility::for($permanent, ShopIntegrationCredentials::forCurrentShop())->deliveryWarning())->toContain('STOP')
        ->and(CustomerSmsSendEligibility::for($permanent, ShopIntegrationCredentials::forCurrentShop())->deliveryWarning())->not->toContain('before texting again');
});

test('workspace shows Texting unavailable for a landline destination conversation', function () {
    companionBindSmsTransport();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Land',
        'phone' => '7195553031',
        'email' => 'pat.land@example.com',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    companionCapability('7195553031', false, 'landline');

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195553031',
        'status' => ConversationStatus::Open,
    ]);
    app(ConversationLinker::class)->link($conversation, $customer);
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'participant_type' => ConversationParticipantType::Customer,
            'customer_id' => $customer->id,
        ])->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Inbound,
        'body' => 'Hello',
        'occurred_at' => now(),
    ]);

    $page = (string) test()->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'all', 'conversation' => $conversation->id]))
        ->assertOk()
        ->getContent();

    expect($page)->toContain('data-comms-texting="unavailable"')
        ->and($page)->toContain('Texting unavailable')
        ->and($page)->not->toContain('data-comms-texting="allowed"');
});

test('failure classification matches SaaS permanent vs temporary codes', function () {
    $failures = app(SmsDeliveryFailureClassifier::class);

    expect($failures->classify('21610'))->toBe(SmsDeliveryFailureClass::OptOut)
        ->and($failures->classify('30006'))->toBe(SmsDeliveryFailureClass::UnsupportedNumber)
        ->and($failures->allowsRetry(SmsDeliveryFailureClass::TemporaryFailure))->toBeTrue()
        ->and($failures->allowsRetry(SmsDeliveryFailureClass::OptOut))->toBeFalse();
});

test('host signals can emit unavailable and Core presenter keeps standalone Core behavior when unbound', function () {
    expect(app(CommunicationsWorkspaceSignals::class))->toBeInstanceOf(CoreCommunicationsWorkspaceSignals::class);

    $presented = CommunicationsWorkspaceSignalPresenter::permission([
        'texting' => 'unavailable',
        'unavailable_reason' => 'service_disabled',
        'source' => 'Shop texting is not available',
    ]);

    expect($presented['texting'])->toBe('unavailable')
        ->and($presented['tone'])->toBe('warning');
});
