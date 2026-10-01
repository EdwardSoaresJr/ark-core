<?php

use App\Ark\Operations\Communications\TomorrowMorningSchedule;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\ShopSettingsSeeder;


beforeEach(function (): void {
    $this->seed(ShopSettingsSeeder::class);
    ShopSettings::current()->update([
        'shop_timezone' => 'America/Denver',
    ]);
    config()->set('app.timezone', 'UTC');
});

test('before 8am on an open weekday uses that morning', function () {
    // Tuesday before 8am → Tuesday 8am
    $now = CarbonImmutable::parse('2026-07-21 01:00:00', 'America/Denver');

    $scheduled = TomorrowMorningSchedule::nextInstant($now, 'America/Denver');

    expect($scheduled->utc()->format('Y-m-d H:i:s'))->toBe('2026-07-21 14:00:00')
        ->and(ShopDisplayTimezone::format($scheduled, 'Y-m-d H:i:s'))->toBe('2026-07-21 08:00:00');
});

test('after 8am on an open weekday uses next open morning', function () {
    // Tuesday after 8am → Wednesday 8am
    $now = CarbonImmutable::parse('2026-07-21 08:15:00', 'America/Denver');

    $scheduled = TomorrowMorningSchedule::nextInstant($now, 'America/Denver');

    expect(ShopDisplayTimezone::format($scheduled, 'Y-m-d H:i:s'))->toBe('2026-07-22 08:00:00');
});

test('saturday night schedules monday morning when weekend is closed', function () {
    // Saturday 9pm Denver → Monday 8am (Sun closed in defaults)
    $now = CarbonImmutable::parse('2026-08-15 21:00:00', 'America/Denver');

    $scheduled = TomorrowMorningSchedule::nextInstant($now, 'America/Denver');

    expect(ShopDisplayTimezone::format($scheduled, 'D Y-m-d H:i:s'))->toBe('Mon 2026-08-17 08:00:00');
});

test('upcoming open mornings skip closed weekend days', function () {
    $now = CarbonImmutable::parse('2026-08-15 21:00:00', 'America/Denver');

    $slots = TomorrowMorningSchedule::upcomingOpenMornings(3, $now, 'America/Denver');

    expect($slots)->toHaveCount(3)
        ->and($slots[0]['day_key'])->toBe('2026-08-17')
        ->and($slots[1]['day_key'])->toBe('2026-08-18')
        ->and($slots[2]['day_key'])->toBe('2026-08-19')
        ->and($slots[0]['label'])->toContain('Mon');
});
