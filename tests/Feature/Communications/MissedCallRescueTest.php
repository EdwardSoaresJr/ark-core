<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\Jobs\SendMissedCallRescueSmsJob;
use App\Ark\Operations\Telephony\MissedCallRescueCopy;
use App\Ark\Operations\Telephony\ScheduleMissedCallRescueAction;
use App\Ark\Operations\Telephony\SendMissedCallRescueSmsAction;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Cache::flush();

    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['missed_call_rescue_enabled'] = true;
    $flow['missed_call_rescue_delay_seconds'] = 30;
    $flow['missed_call_rescue_cooldown_minutes'] = 60;
    $flow['missed_call_rescue_text_open'] = 'Open rescue from {{business.name}} to {{caller.number}}.';
    $flow['missed_call_rescue_text_closed'] = 'Closed rescue from {{business.name}}.';

    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'shop_timezone' => 'America/Denver',
        'telephony_inbound_number' => '7195559999',
        'telephony_call_flow' => $flow,
    ]);
    ShopSettings::forgetCurrent();
});

function missedCallRescueSession(?Customer $customer = null, string $sid = 'CArescue001'): CallSession
{
    return CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => $sid,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551234',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551234',
        'normalized_to' => '7195559999',
        'customer_id' => $customer?->id,
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinutes(2),
        'ended_at' => now()->subMinute(),
    ]);
}

test('missed call rescue sends system sms into conversation when enabled', function () {
    // Open-hours template only applies while the shop is open — pin a
    // weekday mid-morning so the test does not depend on the wall clock.
    $this->travelTo(now('America/Denver')->next('Tuesday')->setTime(10, 0));

    bindFakeOutboundSms('SMrescue001');
    seedMobileSmsCapability('7195551234');

    $customer = Customer::query()->create([
        'first_name' => 'Sam',
        'last_name' => 'Caller',
        'phone' => '7195551234',
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    $session = missedCallRescueSession($customer);
    $sent = app(SendMissedCallRescueSmsAction::class)->execute($session);

    expect($sent)->toBeTrue();

    $message = ConversationMessage::query()->with('participant')->sole();

    expect($message->body)->toContain('Open rescue from Demo Auto Repair')
        ->and($message->body)->toContain('(719) 555-1234')
        ->and($message->metadata['missed_call_rescue'] ?? null)->toBeTrue()
        ->and($message->metadata['call_session_id'] ?? null)->toBe($session->id)
        ->and($message->participant?->participant_type)->toBe(ConversationParticipantType::System);
});

test('missed call rescue does not send when disabled', function () {
    $flow = ShopSettings::current()->telephony_call_flow;
    $flow['missed_call_rescue_enabled'] = false;
    ShopSettings::current()->update(['telephony_call_flow' => $flow]);
    ShopSettings::forgetCurrent();

    $session = missedCallRescueSession();
    $sent = app(SendMissedCallRescueSmsAction::class)->execute($session);

    expect($sent)->toBeFalse()
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('missed call rescue skips opted-out customers', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Opt',
        'last_name' => 'Out',
        'phone' => '7195551234',
        'sms_consent_status' => CustomerSmsConsentStatus::OptedOut,
    ]);

    $session = missedCallRescueSession($customer);
    $sent = app(SendMissedCallRescueSmsAction::class)->execute($session);

    expect($sent)->toBeFalse()
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('missed call rescue respects cooldown after a prior rescue', function () {
    bindFakeOutboundSms('SMrescue002');
    seedMobileSmsCapability('7195551234');

    $session = missedCallRescueSession(sid: 'CArescue002');
    expect(app(SendMissedCallRescueSmsAction::class)->execute($session))->toBeTrue();

    $second = missedCallRescueSession(sid: 'CArescue003');
    expect(app(SendMissedCallRescueSmsAction::class)->execute($second))->toBeFalse()
        ->and(ConversationMessage::query()->count())->toBe(1);
});

test('schedule missed call rescue dispatches delayed job once', function () {
    Queue::fake();

    $session = missedCallRescueSession(sid: 'CArescue004');

    app(ScheduleMissedCallRescueAction::class)->execute($session);
    app(ScheduleMissedCallRescueAction::class)->execute($session);

    Queue::assertPushed(SendMissedCallRescueSmsJob::class, 1);
    Queue::assertPushed(SendMissedCallRescueSmsJob::class, function (SendMissedCallRescueSmsJob $job) use ($session): bool {
        return $job->callSessionId === $session->id
            && $job->delay !== null;
    });
});

test('schedule missed call rescue does nothing when disabled', function () {
    Queue::fake();

    $flow = ShopSettings::current()->telephony_call_flow;
    $flow['missed_call_rescue_enabled'] = false;
    ShopSettings::current()->update(['telephony_call_flow' => $flow]);
    ShopSettings::forgetCurrent();

    app(ScheduleMissedCallRescueAction::class)->execute(missedCallRescueSession(sid: 'CArescue005'));

    Queue::assertNothingPushed();
});

test('missed call rescue copy uses closed template outside hours', function () {
    $flowConfig = ShopSettings::current()->telephony_call_flow;
    foreach (TelephonyCallFlowSettings::WEEKDAYS as $day) {
        $flowConfig['weekly_hours'][$day] = ['enabled' => false, 'open' => '09:00', 'close' => '18:00'];
    }
    ShopSettings::current()->update(['telephony_call_flow' => $flowConfig]);
    ShopSettings::forgetCurrent();

    $session = missedCallRescueSession(sid: 'CArescue006');
    $body = MissedCallRescueCopy::bodyFor($session, TelephonyCallFlowSettings::fromShopSettings());

    expect($body)->toBe('Closed rescue from Demo Auto Repair.');
});

test('admin can save missed call rescue settings on hours tab', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.customer-messaging.update'), [
            'telephony_call_flow' => [
                'missed_call_rescue_enabled' => '1',
                'missed_call_rescue_delay_seconds' => 90,
                'missed_call_rescue_cooldown_minutes' => 45,
                'missed_call_rescue_text_open' => 'Sorry we missed you at {{business.name}}.',
                'missed_call_rescue_text_closed' => 'Closed — {{business.name}} will reply soon.',
            ],
        ])
        ->assertRedirect();

    ShopSettings::forgetCurrent();
    $flow = TelephonyCallFlowSettings::fromShopSettings(ShopSettings::current());

    expect($flow->missedCallRescueEnabled())->toBeTrue()
        ->and($flow->missedCallRescueDelaySeconds())->toBe(90)
        ->and($flow->missedCallRescueCooldownMinutes())->toBe(45)
        ->and($flow->missedCallRescueTextOpen())->toContain('Sorry we missed you');
});

test('missed call rescue skips landline when stored capability says not sms capable', function () {
    bindFakeOutboundSms('SMshouldnot');

    PhoneSmsCapability::query()->create([
        'normalized_phone' => '7195551234',
        'valid' => true,
        'line_type' => 'landline',
        'carrier_name' => 'CenturyLink',
        'sms_capable' => false,
        'reason' => 'Landline (CenturyLink) — cannot receive SMS.',
        'checked_at' => now(),
    ]);

    $session = missedCallRescueSession(sid: 'CArescueland');
    $sent = app(SendMissedCallRescueSmsAction::class)->execute($session);

    expect($sent)->toBeFalse()
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195551234')?->sms_capable)->toBeFalse();
});

test('missed call rescue does not send when messaging transport is not configured', function () {
    $session = missedCallRescueSession(sid: 'CArescuenotcfg');
    $sent = app(SendMissedCallRescueSmsAction::class)->execute($session);

    expect($sent)->toBeFalse()
        ->and(ConversationMessage::query()->count())->toBe(0);
});
