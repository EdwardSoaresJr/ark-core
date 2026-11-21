<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceSignalPresenter;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\PhoneSmsOptOut;
use App\Ark\Operations\Messaging\ProcessInboundSmsConsentAction;
use App\Ark\Operations\Messaging\RecordCustomerSmsDeliveryStatusAction;
use App\Ark\Operations\Messaging\SendOutboundContactMessageAction;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\Messaging\SmsDeliveryFailureClass;
use App\Ark\Operations\Messaging\SmsDeliveryFailureClassifier;
use App\Ark\Operations\Messaging\TwilioOutboundSmsTransport;
use App\Ark\Operations\Observations\OperationalObservationStreamEntry;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['services.twilio.account_sid' => 'ACsynthetic', 'services.twilio.auth_token' => 'synthetic-token']);
});

function failureCustomer(string $phone, CustomerSmsConsentStatus $consent = CustomerSmsConsentStatus::Subscribed): Customer
{
    return Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'Customer',
        'phone' => $phone,
        'sms_consent_status' => $consent,
    ]);
}

function failureConversation(string $phone, string $sid): Conversation
{
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $phone,
        'status' => ConversationStatus::Open,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'participant_type' => ConversationParticipantType::Customer,
        ])->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'Synthetic outbound.',
        'occurred_at' => now(),
        'metadata' => ['twilio_message_sid' => $sid],
    ]);

    return $conversation;
}

function failureEntries(): Collection
{
    return OperationalObservationStreamEntry::query()
        ->where('observation_type', OperationalObservationType::CommunicationDeliveryFailed->value)
        ->get();
}

test('classifier sorts provider codes and only blocks retry for opt-out and unsupported numbers', function () {
    $classifier = new SmsDeliveryFailureClassifier;

    expect($classifier->classify('21610'))->toBe(SmsDeliveryFailureClass::OptOut)
        ->and($classifier->classify('21614'))->toBe(SmsDeliveryFailureClass::UnsupportedNumber)
        ->and($classifier->classify('21612'))->toBe(SmsDeliveryFailureClass::UnsupportedNumber)
        ->and($classifier->classify('30006'))->toBe(SmsDeliveryFailureClass::UnsupportedNumber)
        ->and($classifier->classify('21211'))->toBe(SmsDeliveryFailureClass::InvalidDestination)
        ->and($classifier->classify('30003'))->toBe(SmsDeliveryFailureClass::TemporaryFailure)
        ->and($classifier->classify('30005'))->toBe(SmsDeliveryFailureClass::TemporaryFailure)
        ->and($classifier->classify('30008'))->toBe(SmsDeliveryFailureClass::TemporaryFailure)
        ->and($classifier->classify('99999'))->toBe(SmsDeliveryFailureClass::Unknown)
        ->and($classifier->classify(null))->toBe(SmsDeliveryFailureClass::Unknown)
        ->and($classifier->classify(null, 'Cannot be a landline'))->toBe(SmsDeliveryFailureClass::UnsupportedNumber)
        ->and($classifier->allowsRetry(SmsDeliveryFailureClass::OptOut))->toBeFalse()
        ->and($classifier->allowsRetry(SmsDeliveryFailureClass::UnsupportedNumber))->toBeFalse()
        ->and($classifier->allowsRetry(SmsDeliveryFailureClass::TemporaryFailure))->toBeTrue()
        ->and($classifier->allowsRetry(SmsDeliveryFailureClass::Unknown))->toBeTrue()
        ->and($classifier->staffGuidance(SmsDeliveryFailureClass::UnsupportedNumber, '30006'))
        ->toContain('may not support text messages')
        ->not->toContain('landline');
});

test('a 30006 failure on a secondary number leaves the primary number eligible', function () {
    $customer = failureCustomer('7195553001');

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMdelfail01', 'undelivered', '30006', '+17195553002');

    $primary = CustomerSmsSendEligibility::for($customer->fresh(), ShopIntegrationCredentials::forCurrentShop());
    $secondary = CustomerSmsSendEligibility::for($customer->fresh(), ShopIntegrationCredentials::forCurrentShop(), '7195553002');

    expect($primary->canSend())->toBeTrue()
        ->and($primary->deliveryWarning())->toBeNull()
        ->and($secondary->canSend())->toBeFalse()
        ->and($secondary->blockReason())->toContain('may not support SMS')
        ->and($customer->fresh()->last_sms_delivery_status)->toBeNull()
        ->and($customer->fresh()->last_sms_error_code)->toBeNull();
});

test('a 21610 failure opts the number out and blocks contact and customer sends', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = failureCustomer('7195553011');
    $conversation = failureConversation('7195553011', 'SMdelfail02');

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMdelfail02', 'undelivered', '21610', '7195553011');

    expect(PhoneSmsOptOut::isActive('7195553011'))->toBeTrue()
        ->and(PhoneSmsOptOut::query()->where('normalized_phone', '7195553011')->first()->error_code)->toBe('21610')
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195553011'))->toBeNull()
        ->and(fn () => app(SendOutboundContactMessageAction::class)->execute($conversation, $advisor, 'Hello'))
        ->toThrow(RuntimeException::class, 'opted out')
        ->and(fn () => app(SendOutboundMessageAction::class)->execute(customer: $customer, actor: $advisor, body: 'Hello'))
        ->toThrow(RuntimeException::class, 'opted out')
        ->and($transport->sent)->toBeEmpty();
});

test('a temporary failure does not mark the number not sms capable', function () {
    $customer = failureCustomer('7195553021');

    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMdelfail03', 'failed', '30003', '7195553021');
    app(RecordCustomerSmsDeliveryStatusAction::class)->execute($customer, 'SMdelfail04', 'failed', '99999', '7195553021');

    expect(PhoneSmsCapability::findByNormalizedPhone('7195553021'))->toBeNull()
        ->and(PhoneSmsOptOut::isActive('7195553021'))->toBeFalse();
});

test('a stop from a number with no customer is remembered and blocks a later customerless send', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $result = app(ProcessInboundSmsConsentAction::class)->execute('+17195553031', 'STOP', 'SMstop0001');

    expect($result['handled'])->toBeTrue()
        ->and(PhoneSmsOptOut::isActive('7195553031'))->toBeTrue()
        ->and(fn () => app(SendOutboundContactMessageAction::class)->execute(
            Conversation::query()->create([
                'contact_surface' => ConversationContactSurface::Phone,
                'contact_address' => '7195553031',
                'status' => ConversationStatus::Open,
            ]),
            $advisor,
            'Hello',
        ))->toThrow(RuntimeException::class, 'opted out')
        ->and($transport->sent)->toBeEmpty();

    $start = app(ProcessInboundSmsConsentAction::class)->execute('+17195553031', 'START');

    expect($start['handled'])->toBeTrue()
        ->and(PhoneSmsOptOut::isActive('7195553031'))->toBeFalse();
});

test('an opted-out customer owning the number blocks a send to that number from another customer', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    failureCustomer('7195553042', CustomerSmsConsentStatus::OptedOut);
    $sender = failureCustomer('7195553041');

    expect(fn () => app(SendOutboundMessageAction::class)->execute(customer: $sender, actor: $advisor, body: 'Hi', toPhone: '7195553042'))
        ->toThrow(RuntimeException::class, 'opted out')
        ->and($transport->sent)->toBeEmpty();
});

test('a delivery failure puts a waiting conversation back in needs attention and emits once', function () {
    $customer = failureCustomer('7195553051');
    $conversation = failureConversation('7195553051', 'SMdelfail05');
    $work = app(ConversationWork::class);
    $work->followUp($conversation);

    expect($conversation->fresh()->waiting_on)->toBe(ConversationWaitingOn::Customer);

    $record = app(RecordCustomerSmsDeliveryStatusAction::class);
    $record->execute($customer, 'SMdelfail05', 'undelivered', '30003', '7195553051');

    $conversation = $conversation->fresh();
    expect($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($work->lane($conversation))->toBe('needs')
        ->and(failureEntries())->toHaveCount(1);

    $entry = failureEntries()->first();
    expect($entry->dedupe_key)->toStartWith('sms_delivery_failed:message:')
        ->and($entry->conversation_id)->toBe($conversation->id)
        ->and($entry->metadata['error_code'])->toBe('30003')
        ->and($entry->metadata['failure_class'])->toBe('temporary_failure')
        ->and($entry->metadata['recipient'])->toBe('7195553051')
        ->and($entry->metadata['conversation_message_id'])->toBeInt();

    $record->execute($customer, 'SMdelfail05', 'undelivered', '30003', '7195553051');

    expect(failureEntries())->toHaveCount(1);
});

test('resolving the conversation acknowledges the failure and a replayed callback does not reopen it', function () {
    $customer = failureCustomer('7195553061');
    $conversation = failureConversation('7195553061', 'SMdelfail06');
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $record = app(RecordCustomerSmsDeliveryStatusAction::class);

    $record->execute($customer, 'SMdelfail06', 'failed', '21610', '7195553061');
    $work->resolve($conversation->fresh(), $advisor);

    expect(failureEntries()->first()->resolved_at)->not->toBeNull()
        ->and(failureEntries()->first()->resolved_by_user_id)->toBe($advisor->id)
        ->and($conversation->fresh()->status)->toBe(ConversationStatus::Resolved);

    $record->execute($customer, 'SMdelfail06', 'failed', '21610', '7195553061');

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Resolved)
        ->and(failureEntries())->toHaveCount(1)
        ->and(failureEntries()->first()->resolved_at)->not->toBeNull();
});

test('workspace delivery guidance never says to resend after an opt-out or unsupported number', function () {
    $optOut = CommunicationsWorkspaceSignalPresenter::delivery(['status' => 'undelivered', 'error_code' => '21610']);
    $unsupported = CommunicationsWorkspaceSignalPresenter::delivery(['status' => 'undelivered', 'error_code' => '30006']);
    $temporary = CommunicationsWorkspaceSignalPresenter::delivery(['status' => 'failed', 'error_code' => '30003']);

    expect($optOut['detail'])->toContain('Carrier error 21610')->toContain('Do not text it again')->not->toContain('send it again')
        ->and($unsupported['detail'])->toContain('Carrier error 30006')->toContain('may not support text messages')
        ->not->toContain('send it again')->not->toContain('sending it again')->not->toContain('landline')
        ->and($temporary['detail'])->toContain('Carrier error 30003')->toContain('sending it again later');
});

test('workspace permission shows unavailable texting instead of allowed', function () {
    foreach (['landline', 'unsupported', 'disabled', 'temporary_failure'] as $texting) {
        $permission = CommunicationsWorkspaceSignalPresenter::permission(['texting' => $texting]);

        expect($permission['label'])->toBe('Texting unavailable')->not->toBe('Texting allowed');
    }

    expect(CommunicationsWorkspaceSignalPresenter::permission(['texting' => 'allowed'])['label'])->toBe('Texting allowed');
});

test('twilio transport puts the provider code and message in the exception', function () {
    config(['services.twilio.from' => '7195559999']);
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 21610, 'message' => 'Attempt to send to unsubscribed recipient'], 400)]);

    expect(fn () => app(TwilioOutboundSmsTransport::class)->send('7195553071', 'Hello'))
        ->toThrow(RuntimeException::class, 'Twilio rejected the outbound message (21610): Attempt to send to unsubscribed recipient');
});
