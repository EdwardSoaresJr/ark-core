<?php

/**
 * H0.3 — Break-it cases customers actually create.
 *
 * @see docs/communications/ark-conversations-v1.md
 */

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
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

test('H0 break-it: missed call after outbound SMS must not leave conflicting Turn', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195557001',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    $phone = PhoneNumber::normalize((string) $customer->phone);

    $recorder = app(ConversationRecorder::class);
    $recorder->recordOutboundSms($customer, $advisor, 'Estimate is ready.', 'SM-break-1');

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-break-missed',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195557001',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    // Doctrine: Turn must recompute to Waiting on Shop — conflict = H0 fail.
    ConversationsH0::assertSixOnes($customer, null, $advisor, 'Missed call after SMS');
});

test('H0 break-it: duplicate inbound SMS webhook does not create second Thread', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195557002',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $recorder = app(ConversationRecorder::class);

    $recorder->recordInboundSms($phone, 'Hello', 'SM-dupe-same', $customer);
    try {
        $recorder->recordInboundSms($phone, 'Hello', 'SM-dupe-same', $customer);
    } catch (Throwable) {
        // Idempotent implementations may throw or no-op.
    }

    expect(
        Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('contact_address', $phone)
            ->count()
    )->toBe(1);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    ConversationsH0::assertSixOnes($customer, null, $advisor, 'Duplicate inbound webhook');
});

test('H0 break-it: phone change must not invent a second relationship triage identity without link', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195557003',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    $oldPhone = PhoneNumber::normalize('7195557003');
    $recorder = app(ConversationRecorder::class);
    $recorder->recordInboundSms($oldPhone, 'Old phone', 'SM-old-phone', $customer);

    $customer->forceFill(['phone' => '7195557004'])->save();
    $newPhone = PhoneNumber::normalize('7195557004');
    $recorder->recordInboundSms($newPhone, 'New phone', 'SM-new-phone', $customer);

    // Two contact Conversations may exist (authority). Triage for this customer must still be ≤ 1.
    expect(
        Conversation::query()->where('contact_surface', ConversationContactSurface::Phone)->whereIn('contact_address', [$oldPhone, $newPhone])->count()
    )->toBeGreaterThanOrEqual(1);

    ConversationsH0::assertSixOnes($customer, null, $advisor, 'Phone change');
});

test('H0 break-it: late call session still sorts by operational occurrence', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195557005',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    $phone = PhoneNumber::normalize((string) $customer->phone);
    $recorder = app(ConversationRecorder::class);

    Carbon::setTestNow(Carbon::parse('2026-07-13 18:00:00', 'UTC'));
    $recorder->recordInboundSms($phone, 'Text first', 'SM-late-1', $customer);

    // Call "happened" earlier but arrives later (webhook delay).
    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-late-call',
        'customer_id' => $customer->id,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195557005',
        'to_number' => '+17195559999',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Completed,
        'started_at' => Carbon::parse('2026-07-13 17:00:00', 'UTC'),
        'worked_at' => Carbon::parse('2026-07-13 17:05:00', 'UTC'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-13 18:05:00', 'UTC'));
    $probe = ConversationsH0::probe($customer, null, $advisor);
    expect($probe['story_chronology_ok'])->toBeTrue('Story order is operational occurrence, not arrival');
    ConversationsH0::assertSixOnes($customer, null, $advisor, 'Late call webhook', requireActiveTurn: false);

    Carbon::setTestNow();
});

test('H0 break-it: second phone number for same customer keeps one triage row', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'phone' => '7195557006',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);
    $recorder = app(ConversationRecorder::class);
    $resolver = app(ConversationResolver::class);

    $primary = PhoneNumber::normalize('7195557006');
    $secondary = PhoneNumber::normalize('7195557007');
    $recorder->recordInboundSms($primary, 'Primary', 'SM-p1', $customer);

    $other = $resolver->forPhone($secondary);
    app(ConversationLinker::class)->link($other, $customer);
    $recorder->recordInboundSms($secondary, 'Secondary', 'SM-p2', $customer);

    ConversationsH0::assertSixOnes($customer, null, $advisor, 'Second phone');
});
