<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresenceSettings;

test('workstation idle lock defaults to five minutes when unset', function (): void {
    $settings = new ShopSettings;

    expect(WorkstationPresenceSettings::fromShopSettings($settings)->idleLockMinutes())->toBe(5)
        ->and(WorkstationPresenceSettings::fromShopSettings($settings)->idleLockEnabled())->toBeTrue();
});

test('workstation idle lock zero disables auto lock', function (): void {
    $settings = new ShopSettings([
        'workstation_idle_lock_minutes' => 0,
    ]);

    expect(WorkstationPresenceSettings::fromShopSettings($settings)->idleLockMinutes())->toBe(0)
        ->and(WorkstationPresenceSettings::fromShopSettings($settings)->idleLockEnabled())->toBeFalse();
});
