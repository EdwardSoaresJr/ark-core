<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Staff\Duty\StaffDuty;
use App\Ark\Operations\Staff\Duty\StaffDutyEndReason;
use App\Ark\Operations\Staff\Duty\StaffDutyKind;
use App\Ark\Operations\Staff\Duty\StaffDutyPeriod;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyRingGroup;
use App\Ark\Operations\Telephony\TelephonyRingSchedule;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.ark_platform.communications_authority', false);
    TelephonyEndpoint::query()->delete();
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function at(string $local): CarbonImmutable
{
    $moment = CarbonImmutable::parse($local, 'America/Denver');
    Carbon::setTestNow($moment);
    CarbonImmutable::setTestNow($moment);

    return $moment;
}

function onDutyCell(User $user, int $afterMinutes, string $destination = '7195550401', int $delay = 0): TelephonyEndpoint
{
    return TelephonyEndpoint::query()->create([
        'name' => "{$user->name} Cell",
        'type' => TelephonyEndpointType::Cell,
        'destination' => $destination,
        'user_id' => $user->id,
        'ring_schedule' => TelephonyRingSchedule::OnDuty,
        'on_duty_after_minutes' => $afterMinutes,
        'ring_delay_seconds' => $delay,
        'enabled' => true,
        'position' => 0,
    ]);
}

function ringsNow(TelephonyEndpoint $endpoint): bool
{
    return app(TelephonyRingGroup::class)->endpointsForIncomingRing()->contains('id', $endpoint->id);
}

test('an on-duty cell rings only after the person has been signed in for its threshold', function (): void {
    $edward = User::factory()->create(['phone' => '7195550401']);
    $cell = onDutyCell($edward, afterMinutes: 15);

    at('2026-10-14 09:00');
    expect(ringsNow($cell))->toBeFalse();

    app(StaffDuty::class)->startFromSignIn($edward);
    at('2026-10-14 09:10');
    expect(ringsNow($cell))->toBeFalse();

    at('2026-10-14 09:16');
    expect(ringsNow($cell))->toBeTrue();

    $xml = $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAonduty0001',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk()->getContent();

    expect($xml)->toContain('<Number')->and($xml)->toContain('+17195550401');
});

test('browser inactivity never takes a person off duty', function (): void {
    $edward = User::factory()->create(['phone' => '7195550401', 'last_seen_at' => null]);
    $cell = onDutyCell($edward, afterMinutes: 0);

    at('2026-10-14 08:30');
    app(StaffDuty::class)->startFromSignIn($edward);

    at('2026-10-14 15:30');
    $edward->forceFill(['last_seen_at' => now()->subHours(6)])->saveQuietly();

    expect(ringsNow($cell))->toBeTrue();
});

test('signing in again does not restart or extend duty', function (): void {
    $edward = User::factory()->create();
    $cell = onDutyCell($edward, afterMinutes: 30);
    $duty = app(StaffDuty::class);

    at('2026-10-14 09:00');
    $first = $duty->startFromSignIn($edward);
    at('2026-10-14 09:50');
    $second = $duty->startFromSignIn($edward);

    at('2026-10-14 09:55');
    expect($second->id)->toBe($first->id)
        ->and(StaffDutyPeriod::query()->count())->toBe(1)
        ->and(ringsNow($cell))->toBeTrue();
});

test('signing out ends duty and the next sign-in starts the threshold again', function (): void {
    $edward = User::factory()->create();
    $cell = onDutyCell($edward, afterMinutes: 10);
    $duty = app(StaffDuty::class);

    at('2026-10-14 09:00');
    $duty->startFromSignIn($edward);
    at('2026-10-14 11:00');
    $duty->end($edward, StaffDutyEndReason::SignOut);

    expect(ringsNow($cell))->toBeFalse()
        ->and(StaffDutyPeriod::query()->sole()->end_reason)->toBe(StaffDutyEndReason::SignOut);

    at('2026-10-14 11:05');
    $duty->startFromSignIn($edward);
    at('2026-10-14 11:10');
    expect(ringsNow($cell))->toBeFalse();
    at('2026-10-14 11:15');
    expect(ringsNow($cell))->toBeTrue();
});

test('closing time ends duty for a session left open overnight', function (): void {
    $edward = User::factory()->create();
    $cell = onDutyCell($edward, afterMinutes: 0);
    $duty = app(StaffDuty::class);

    at('2026-10-13 09:00');
    $duty->startFromSignIn($edward);

    at('2026-10-14 09:05');
    expect(ringsNow($cell))->toBeFalse();

    $duty->startFromSignIn($edward);
    $periods = StaffDutyPeriod::query()->orderBy('id')->get();

    expect($periods)->toHaveCount(2)
        ->and($periods[0]->end_reason)->toBe(StaffDutyEndReason::ShopClosed)
        ->and($periods[0]->ended_at->timezone('America/Denver')->format('Y-m-d H:i'))->toBe('2026-10-13 18:00')
        ->and(ringsNow($cell))->toBeTrue();
});

test('the shop maximum duty length ends duty without any browser signal', function (): void {
    $settings = ShopSettings::current();
    $settings->persistTrusted(['telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), ['duty_max_hours' => 2])]);
    $edward = User::factory()->create();
    $cell = onDutyCell($edward, afterMinutes: 0);

    at('2026-10-14 09:00');
    app(StaffDuty::class)->startFromSignIn($edward);

    at('2026-10-14 10:59');
    expect(ringsNow($cell))->toBeTrue();
    at('2026-10-14 11:01');
    expect(ringsNow($cell))->toBeFalse();
});

test('after-hours coverage must be authorized and is not cut off at closing time', function (): void {
    $edward = User::factory()->create();
    $owner = User::factory()->create();
    $duty = app(StaffDuty::class);

    at('2026-10-14 17:00');
    expect(fn () => $duty->start($edward, StaffDutyKind::AfterHoursCoverage))->toThrow(InvalidArgumentException::class);

    $duty->start($edward, StaffDutyKind::AfterHoursCoverage, endsAt: at('2026-10-14 17:00')->setTime(21, 0), authorizedBy: $owner);

    at('2026-10-14 20:00');
    expect($duty->isOnDuty($edward->id))->toBeTrue();
    at('2026-10-14 21:01');
    expect($duty->isOnDuty($edward->id))->toBeFalse();
});

test('a cell endpoint keeps ringing its own number when the staff profile phone changes', function (): void {
    $edward = User::factory()->create(['phone' => '7195550401']);
    $cell = onDutyCell($edward, afterMinutes: 0, destination: '7195550777');

    $edward->forceFill(['phone' => '7195550999'])->save();

    expect($cell->fresh()->dialDestination())->toBe('7195550777');

    $legacy = TelephonyEndpoint::query()->create([
        'name' => 'Legacy Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '',
        'user_id' => $edward->id,
        'ring_schedule' => TelephonyRingSchedule::Always,
        'enabled' => true,
        'position' => 1,
    ]);
    expect($legacy->dialDestination())->toBe('7195550999');
});

test('delayed ringing still applies to an on-duty cell', function (): void {
    $edward = User::factory()->create();
    $cell = onDutyCell($edward, afterMinutes: 0, delay: 10);

    at('2026-10-14 09:00');
    app(StaffDuty::class)->startFromSignIn($edward);

    $group = app(TelephonyRingGroup::class);
    expect($group->staggeredDelayTiers()->all())->toBe([10])
        ->and($group->endpointsForStaggeredTier(0, 10)->pluck('id')->all())->toBe([$cell->id]);
});

test('the login form starts duty and the logout button ends it', function (): void {
    $edward = User::factory()->create(['email' => 'edward@ark.test'])->assignRole(ArkRole::Advisor->value);

    at('2026-10-14 09:00');
    $this->post(route('login'), ['email' => 'edward@ark.test', 'password' => 'password'])->assertRedirect();

    $period = StaffDutyPeriod::query()->sole();
    expect($period->user_id)->toBe($edward->id)
        ->and($period->kind)->toBe(StaffDutyKind::SignIn)
        ->and($period->ended_at)->toBeNull();

    at('2026-10-14 12:00');
    $this->post(route('logout'))->assertRedirect('/');

    expect($period->fresh()->end_reason)->toBe(StaffDutyEndReason::SignOut);
});

test('saving ring settings keeps the number entered on the cell endpoint and its duty threshold', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $edward = User::factory()->create(['phone' => '7195550999']);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'ring',
            'endpoints' => [[
                'name' => 'Edward Cell',
                'type' => 'cell',
                'user_id' => (string) $edward->id,
                'destination' => '(719) 555-0777',
                'ring_schedule' => 'on_duty',
                'on_duty_after_minutes' => '20',
                'ring_delay_seconds' => '10',
                'enabled' => '1',
            ]],
        ])
        ->assertSessionHasNoErrors();

    $endpoint = TelephonyEndpoint::query()->sole();
    expect($endpoint->destination)->toBe('7195550777')
        ->and($endpoint->ring_schedule)->toBe(TelephonyRingSchedule::OnDuty)
        ->and($endpoint->onDutyAfterMinutes())->toBe(20)
        ->and($endpoint->ring_delay_seconds)->toBe(10);
});
