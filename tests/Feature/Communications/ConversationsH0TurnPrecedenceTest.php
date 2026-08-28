<?php

/**
 * H0.2.1 — Communication event precedence regressions.
 *
 * Newest unresolved inbound customer communication owns Waiting on Shop.
 * Transport must never matter. Shop action resolves.
 */

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\ConversationsH0;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['telephony_inbound_number' => '7195559999']);
    ShopSettings::forgetCurrent();
});

function precedenceCustomer(string $phone): Customer
{
    return Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => $phone,
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
}

function phoneConversation(Customer $customer): Conversation
{
    $phone = PhoneNumber::normalize((string) $customer->phone);

    return Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', $phone)
        ->sole();
}

test('precedence: outbound SMS then inbound call stays Waiting on Shop', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = precedenceCustomer('7195558101');
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $recorder = app(ConversationRecorder::class);

    Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', 'UTC'));
    $recorder->recordOutboundSms($customer, $advisor, 'Estimate ready', 'SM-prec-1');

    Carbon::setTestNow(Carbon::parse('2026-07-13 10:05:00', 'UTC'));
    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-prec-missed',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558101',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    expect(phoneConversation($customer)->waiting_on)->toBe(ConversationWaitingOn::Shop);
    ConversationsH0::assertSixOnes($customer, null, $advisor, 'Outbound then missed call');
    Carbon::setTestNow();
});

test('precedence: stacked inbound SMS call voicemail portal stays one Waiting on Shop', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = precedenceCustomer('7195558102');
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $recorder = app(ConversationRecorder::class);

    Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', 'UTC'));
    $recorder->recordOutboundSms($customer, $advisor, 'Hi', 'SM-stack-0');

    Carbon::setTestNow(Carbon::parse('2026-07-13 10:01:00', 'UTC'));
    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-stack-1',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558102',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-13 10:02:00', 'UTC'));
    $recorder->recordInboundSms($phone, 'Text after call', 'SM-stack-2', $customer);

    Carbon::setTestNow(Carbon::parse('2026-07-13 10:03:00', 'UTC'));
    CallSession::query()->where('provider_call_sid', 'CA-stack-1')->first()->forceFill([
        'voicemail_url' => 'https://api.twilio.com/vm.wav',
    ])->save();

    expect(phoneConversation($customer)->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and(ConversationsH0::probe($customer, null, $advisor)['turn'])->toBe('waiting_on_shop')
        ->and(ConversationsH0::probe($customer, null, $advisor)['turn_conflict'])->toBeFalse();

    Carbon::setTestNow();
});

test('precedence: advisor outbound call after inbound resolves to Waiting on Customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = precedenceCustomer('7195558103');
    $phone = PhoneNumber::normalize((string) $customer->phone);

    Carbon::setTestNow(Carbon::parse('2026-07-13 11:00:00', 'UTC'));
    $inbound = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-return-in',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558103',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    expect(phoneConversation($customer)->waiting_on)->toBe(ConversationWaitingOn::Shop);

    Carbon::setTestNow(Carbon::parse('2026-07-13 11:10:00', 'UTC'));
    $inbound->forceFill([
        'worked_at' => now(),
        'status' => CallSessionStatus::Completed,
    ])->save();

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-return-out',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '+17195559999',
        'to_number' => '+17195558103',
        'normalized_to' => $phone,
        'normalized_from' => '7195559999',
        'status' => CallSessionStatus::Completed,
        'started_at' => now(),
        'worked_at' => now(),
        'owned_by_user_id' => $advisor->id,
    ]);

    expect(phoneConversation($customer)->waiting_on)->toBe(ConversationWaitingOn::Customer);
    Carbon::setTestNow();
});

test('precedence: advisor SMS after inbound call resolves Turn (explicit)', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = precedenceCustomer('7195558104');
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $recorder = app(ConversationRecorder::class);

    Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00', 'UTC'));
    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-sms-resolves',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558104',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    expect(phoneConversation($customer)->waiting_on)->toBe(ConversationWaitingOn::Shop);

    // Explicit doctrine: outbound SMS resolves the customer's inbound communication need.
    Carbon::setTestNow(Carbon::parse('2026-07-13 12:05:00', 'UTC'));
    $recorder->recordOutboundSms($customer, $advisor, 'Got your call — here is the estimate.', 'SM-resolves-call');

    expect(phoneConversation($customer)->waiting_on)->toBe(ConversationWaitingOn::Customer);
    Carbon::setTestNow();
});
