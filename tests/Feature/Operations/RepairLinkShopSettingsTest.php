<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\PartsCatalogConnections;
use App\Ark\Operations\Parts\PartsCatalogProvider;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Preferences\EstimateToolbarPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('admin can save and update the shop repairlink url', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.repairlink.update'), [
            'repairlink_enabled' => '1',
            'repairlink_url' => 'repairlinkshop.com',
            'repairlink_color' => 'blue',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']));

    $settings = ShopSettings::current()->fresh();

    expect($settings->repairlink_enabled)->toBeTrue()
        ->and($settings->repairlink_url)->toBe('https://repairlinkshop.com')
        ->and(ShopIntegrationCredentials::forCurrentShop()->repairLinkLaunchUrl())->toBe('https://repairlinkshop.com');

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.repairlink.update'), [
            'repairlink_enabled' => '1',
            'repairlink_url' => 'https://www.repairlinkshop.com',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']));

    expect(ShopSettings::current()->fresh()->repairlink_url)->toBe('https://www.repairlinkshop.com')
        ->and(ShopIntegrationCredentials::forCurrentShop()->repairLinkLaunchUrl())->toBe('https://www.repairlinkshop.com');
});

test('advisor cannot modify repairlink settings', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => true,
        'repairlink_url' => 'https://repairlinkshop.com',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    $this->actingAs($advisor)
        ->patch(route('operations.settings.shop.repairlink.update'), [
            'repairlink_enabled' => '0',
            'repairlink_url' => 'https://evil.example',
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    expect(ShopSettings::current()->fresh()->repairlink_url)->toBe('https://repairlinkshop.com')
        ->and(ShopSettings::current()->fresh()->repairlink_enabled)->toBeTrue();
});

test('repairlink settings stay on the current shop row', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $shopA = ShopSettings::current();
    $shopA->persistTrusted([
        'repairlink_enabled' => true,
        'repairlink_url' => 'https://shop-a.example',
    ]);

    $shopB = $shopA->replicate();
    $shopB->shop_name = 'Shop B';
    $shopB->repairlink_url = 'https://shop-b.example';
    $shopB->repairlink_enabled = true;
    $shopB->save();

    ShopSettings::forgetCurrent();

    expect(ShopSettings::current()->id)->toBe($shopA->id)
        ->and(ShopIntegrationCredentials::forCurrentShop()->repairLinkLaunchUrl())->toBe('https://shop-a.example')
        ->and($shopB->fresh()->repairlink_url)->toBe('https://shop-b.example');

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.repairlink.update'), [
            'repairlink_enabled' => '1',
            'repairlink_url' => 'https://www.repairlinkshop.com',
        ])
        ->assertRedirect();

    expect(ShopSettings::current()->fresh()->repairlink_url)->toBe('https://www.repairlinkshop.com')
        ->and($shopB->fresh()->repairlink_url)->toBe('https://shop-b.example')
        ->and($shopB->fresh()->repairlink_enabled)->toBeTrue();
});

test('http and credentialed repairlink urls are rejected', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->patch(route('operations.settings.shop.repairlink.update'), [
            'repairlink_enabled' => '1',
            'repairlink_url' => 'http://repairlinkshop.com',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->assertSessionHasErrors('repairlink_url');

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->patch(route('operations.settings.shop.repairlink.update'), [
            'repairlink_enabled' => '1',
            'repairlink_url' => 'https://user:secret@repairlinkshop.com',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->assertSessionHasErrors('repairlink_url');

    expect(ShopSettings::current()->fresh()->repairlink_url)->toBeNull()
        ->and(ShopSettings::current()->fresh()->repairlink_enabled)->toBeFalse();
});

test('disabled or invalid repairlink configuration cannot open the catalog', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => '1HGCM82633A004352',
        'normalized_vin' => '1HGCM82633A004352',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'RepairLink availability',
    ]);

    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => true,
        'repairlink_url' => 'https://repairlink.test/catalog',
    ]);

    expect(collect(app(PartsCatalogConnections::class)->forRepairOrder($repairOrder))->firstWhere('key', PartsCatalogProvider::RepairLink->value)['can_open'])
        ->toBeTrue();

    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => false,
        'repairlink_url' => 'https://repairlink.test/catalog',
    ]);

    expect(collect(app(PartsCatalogConnections::class)->forRepairOrder($repairOrder))->firstWhere('key', PartsCatalogProvider::RepairLink->value)['can_open'] ?? false)
        ->toBeFalse();

    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => true,
        'repairlink_url' => 'http://repairlink.test/catalog',
    ]);

    expect(collect(app(PartsCatalogConnections::class)->forRepairOrder($repairOrder))->firstWhere('key', PartsCatalogProvider::RepairLink->value)['can_open'] ?? false)
        ->toBeFalse();
});

test('valid repairlink configuration exposes open repairlink without a cart pull', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => true,
        'repairlink_url' => 'https://repairlink.test/catalog',
    ]);

    $advisor = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::RepairLink->value,
    ])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => '1HGCM82633A004352',
        'normalized_vin' => '1HGCM82633A004352',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'RepairLink actions',
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('>PartsTech</span>')
        ->toContain('title="Open PartsTech"')
        ->toContain('>Pull Cart</span>')
        ->toContain('activatePartsCatalogItem(item)')
        ->not->toContain('>RepairLink</span>');
});

test('advisor catalog default stays independent of shop repairlink configuration', function () {
    $advisor = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::RepairLink->value,
    ])->assignRole(ArkRole::Advisor->value);

    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => false,
        'repairlink_url' => null,
    ]);

    expect(EstimateToolbarPreference::resolve($advisor, EstimateToolbarPreference::KIND_PARTS, [
        PartsCatalogProvider::PartsTech->value,
    ]))->toBe(PartsCatalogProvider::PartsTech->value)
        ->and($advisor->fresh()->default_parts_catalog)->toBe(PartsCatalogProvider::RepairLink->value);
});
