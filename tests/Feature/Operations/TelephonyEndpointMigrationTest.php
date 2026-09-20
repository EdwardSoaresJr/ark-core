<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use Illuminate\Support\Facades\DB;


test('migration moves telephony forward target into ring endpoint and clears shop setting', function () {
    TelephonyEndpoint::query()->delete();
    ShopSettings::query()->firstOrCreate([], ['shop_name' => 'Test Shop']);
    DB::table('shop_settings')->update(['telephony_forward_to' => '+17195550199']);

    $migration = require database_path('migrations/2026_06_14_100000_migrate_telephony_forward_to_ring_endpoints.php');
    $migration->up();

    expect(TelephonyEndpoint::query()->count())->toBe(1)
        ->and(TelephonyEndpoint::query()->value('destination'))->toBe('+17195550199')
        ->and(DB::table('shop_settings')->value('telephony_forward_to'))->toBeNull();
});

test('migration skips when ring endpoints already exist', function () {
    TelephonyEndpoint::query()->delete();

    TelephonyEndpoint::query()->create([
        'name' => 'Existing cell',
        'type' => 'cell',
        'destination' => '+17195551001',
        'enabled' => true,
        'position' => 0,
    ]);

    DB::table('shop_settings')->update(['telephony_forward_to' => '+17195550199']);

    $migration = require database_path('migrations/2026_06_14_100000_migrate_telephony_forward_to_ring_endpoints.php');
    $migration->up();

    expect(TelephonyEndpoint::query()->count())->toBe(1)
        ->and(TelephonyEndpoint::query()->value('destination'))->toBe('+17195551001');
});
