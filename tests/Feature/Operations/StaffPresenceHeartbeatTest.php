<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('staff presence heartbeat updates last seen at', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['last_seen_at' => now()->subHour()])->saveQuietly();

    $this->actingAs($advisor)
        ->postJson(route('operations.staff.presence.heartbeat'))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($advisor->fresh()->last_seen_at?->greaterThan(now()->subMinute()))->toBeTrue();
});

test('operations page get does not update last seen at', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['last_seen_at' => now()->subHour()])->saveQuietly();

    $seenAt = $advisor->fresh()->last_seen_at?->toDateTimeString();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();

    expect($advisor->fresh()->last_seen_at?->toDateTimeString())->toBe($seenAt);
});

test('track staff call presence middleware preserves previous last seen without writing', function () {
    $seenAt = now()->subHours(3);
    $advisor = User::factory()->create([
        'last_seen_at' => $seenAt,
    ])->assignRole(ArkRole::Advisor->value);

    $request = \Illuminate\Http\Request::create('/app/workboard', 'GET');
    $request->setUserResolver(fn () => $advisor->fresh());

    app(\App\Http\Middleware\TrackStaffCallPresence::class)->handle(
        $request,
        fn (\Illuminate\Http\Request $handledRequest) => response('ok'),
    );

    $previousLastSeen = $request->attributes->get('operations.previous_last_seen_at');

    expect($previousLastSeen?->toDateTimeString())->toBe($seenAt->toDateTimeString())
        ->and($advisor->fresh()->last_seen_at?->toDateTimeString())->toBe($seenAt->toDateTimeString());
});
