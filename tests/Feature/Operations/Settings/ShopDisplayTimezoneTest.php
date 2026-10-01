<?php

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

test('shop display timezone formats utc timestamps for configured shop timezone', function () {
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopDisplayTimezone::apply();

    $instant = Carbon::parse('2026-06-11 00:15:00', 'UTC');

    expect(ShopDisplayTimezone::format($instant))->toBe('Jun 10, 2026 6:15 PM')
        ->and(config('app.display_timezone'))->toBe('America/Denver');
});

test('shop display timezone rejects missing shop timezone configuration', function () {
    ShopSettings::current()->update(['shop_timezone' => '']);

    expect(fn () => ShopDisplayTimezone::resolve())
        ->toThrow(RuntimeException::class, 'Shop timezone is required');
});
