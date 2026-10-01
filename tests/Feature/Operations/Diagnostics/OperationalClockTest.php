<?php

use App\Ark\Operations\Diagnostics\OperationalClockProjection;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('operational clock projection exposes utc server time and shop display timezone', function () {
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopDisplayTimezone::apply();

    $clock = OperationalClockProjection::resolve();

    expect($clock->phpIsUtc)->toBeTrue()
        ->and($clock->shopTimezone)->toBe('America/Denver')
        ->and($clock->shopShortLabel)->toBe('Denver')
        ->and($clock->serverUtc->timezone->getName())->toBe('UTC')
        ->and($clock->toArray())->toHaveKeys([
            'server_utc_iso',
            'shop_timezone',
            'shop_short_label',
            'php_is_utc',
        ]);
});

test('operational clock projection marks mysql now as utc when it matches utc timestamp', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-only assertion.');
    }

    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopDisplayTimezone::apply();

    $clock = OperationalClockProjection::resolve();

    expect($clock->dbUtc)->not->toBeNull()
        ->and($clock->dbSessionNow)->not->toBeNull()
        ->and($clock->dbMatchesUtc)->toBeTrue();
});

test('settings runtime health section renders operational clock strip', function () {
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopDisplayTimezone::apply();

    $user = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($user)
        ->get(route('operations.settings.shop.edit', ['section' => 'runtime-health']))
        ->assertOk()
        ->assertSee('Runtime health', false)
        ->assertSee('ops-runtime-health-clock', false)
        ->assertSee('Denver', false);
});
