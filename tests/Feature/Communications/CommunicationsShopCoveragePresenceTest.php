<?php

use App\Ark\Operations\Communications\CommunicationsShopProjection;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('enabled cell endpoint alone does not count as floor coverage', function (): void {
    $ben = User::factory()->create(['name' => 'Benjamin Burling'])->assignRole(ArkRole::Technician->value);

    TelephonyEndpoint::query()->create([
        'name' => 'Benjamin Burling Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7198880663',
        'user_id' => $ben->id,
        'enabled' => true,
        'position' => 1,
    ]);

    $row = collect(CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'])
        ->first(fn ($coverageRow) => $coverageRow->name === 'Benjamin Burling');

    expect($row)->not->toBeNull()
        ->and($row->summary)->toBe('Offline');
});

test('workstation sign-in counts as floor coverage', function (): void {
    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);

    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right Counter',
        'current_operator_user_id' => $edward->id,
        'is_active' => true,
    ]);

    $row = collect(CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'])
        ->first(fn ($coverageRow) => $coverageRow->name === 'Alex Rivera');

    expect($row)->not->toBeNull()
        ->and($row->summary)->toBe('Available');
});
