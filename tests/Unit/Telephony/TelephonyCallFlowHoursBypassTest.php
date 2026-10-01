<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Carbon\CarbonImmutable;

test('hours bypass numbers normalize to ten digit values', function (): void {
    $settings = new TelephonyCallFlowSettings([
        'hours_bypass_numbers' => ['(719) 555-1000', '+1 719-555-2000', 'invalid', '12345'],
    ]);

    expect($settings->hoursBypassNumbers())->toBe(['7195551000', '7195552000']);
});

test('listed caller bypasses closed hours without opening the shop for everyone', function (): void {
    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['weekly_hours']['monday']['enabled'] = false;
    $flow['hours_bypass_numbers'] = ['7195551000'];

    $settings = new TelephonyCallFlowSettings($flow);
    $moment = CarbonImmutable::parse('2026-06-15 10:00:00', 'America/Denver');

    expect($settings->isOpenAt($moment))->toBeFalse()
        ->and($settings->isOpenForCaller('7195551000', $moment))->toBeTrue()
        ->and($settings->isOpenForCaller('+17195551234', $moment))->toBeFalse();
});
