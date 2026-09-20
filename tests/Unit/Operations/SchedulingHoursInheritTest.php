<?php

use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Settings\ShopSettings;

test('legacy seeded scheduling hours are detected', function () {
    expect(SchedulingHours::matchesLegacySeed(SchedulingHours::defaultWeekly()))->toBeTrue()
        ->and(SchedulingHours::matchesLegacySeed(null))->toBeFalse()
        ->and(SchedulingHours::isCustom(null))->toBeFalse()
        ->and(SchedulingHours::isCustom([]))->toBeFalse();
});

test('fromBusinessHours normalizes telephony weekly hours', function () {
    $weekly = ShopSettings::defaultTelephonyCallFlow()['weekly_hours'];
    $weekly['saturday'] = ['enabled' => true, 'open' => '09:00', 'close' => '13:00'];

    $hours = SchedulingHours::fromBusinessHours($weekly);

    expect($hours['monday']['enabled'])->toBeTrue()
        ->and($hours['monday']['open'])->toBe('09:00')
        ->and($hours['saturday']['enabled'])->toBeTrue()
        ->and($hours['saturday']['close'])->toBe('13:00')
        ->and($hours['sunday']['enabled'])->toBeFalse();
});

test('normalize strips seconds from clock strings', function () {
    $hours = SchedulingHours::normalize([
        'monday' => ['enabled' => true, 'open' => '08:00:00', 'close' => '17:00:00'],
    ]);

    expect($hours['monday']['open'])->toBe('08:00')
        ->and($hours['monday']['close'])->toBe('17:00');
});
