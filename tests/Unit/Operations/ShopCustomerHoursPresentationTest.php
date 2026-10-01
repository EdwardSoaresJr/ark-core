<?php

use App\Ark\Operations\Settings\ShopCustomerHoursPresentation;

test('shop customer hours compresses consecutive weekdays with matching hours', function () {
    $summary = ShopCustomerHoursPresentation::summary([
        'monday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
        'tuesday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
        'wednesday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
        'thursday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
        'friday' => ['enabled' => true, 'open' => '08:00', 'close' => '17:00'],
        'saturday' => ['enabled' => false, 'open' => '08:00', 'close' => '12:00'],
        'sunday' => ['enabled' => false, 'open' => '08:00', 'close' => '12:00'],
    ]);

    expect($summary)->toBe('Mon–Fri 8:00 AM – 5:00 PM');
});

test('shop customer hours returns null when no enabled days', function () {
    expect(ShopCustomerHoursPresentation::summary([]))->toBeNull();
});
