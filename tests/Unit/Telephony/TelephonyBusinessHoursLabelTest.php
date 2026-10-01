<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;

test('telephony business hours label formats default weekday block', function (): void {
    $settings = new TelephonyCallFlowSettings(ShopSettings::defaultTelephonyCallFlow());

    expect(TelephonyBusinessHoursLabel::fromCallFlow($settings))->toBe('Mon–Fri: 9:00 AM – 6:00 PM · Sat–Sun: Closed');
});

test('telephony business hours label groups split schedules', function (): void {
    $flow = ShopSettings::defaultTelephonyCallFlow();
    $flow['weekly_hours']['friday']['close'] = '17:00';
    $flow['weekly_hours']['saturday'] = ['enabled' => true, 'open' => '09:00', 'close' => '13:00'];

    $settings = new TelephonyCallFlowSettings($flow);

    expect(TelephonyBusinessHoursLabel::fromCallFlow($settings))
        ->toBe('Mon–Thu: 9:00 AM – 6:00 PM · Fri: 9:00 AM – 5:00 PM · Sat: 9:00 AM – 1:00 PM · Sun: Closed');
});

test('telephony business hours label returns closed when no open days', function (): void {
    $flow = ShopSettings::defaultTelephonyCallFlow();

    foreach (TelephonyCallFlowSettings::WEEKDAYS as $day) {
        $flow['weekly_hours'][$day]['enabled'] = false;
    }

    expect(TelephonyBusinessHoursLabel::fromCallFlow(new TelephonyCallFlowSettings($flow)))
        ->toBe('Closed');
});
