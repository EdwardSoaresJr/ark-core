<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\PartsCatalogConnections;
use App\Ark\Operations\Parts\PartsCatalogLinks;
use App\Ark\Operations\Parts\PartsCatalogProvider;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function catalogLinkRepairOrder(): RepairOrder
{
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

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Catalog links',
    ]);
}

test('admin can save a shop nexpart url and a custom catalog link', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.nexpart.update'), [
            'nexpart_enabled' => '1',
            'nexpart_url' => 'nexpart.example',
            'nexpart_color' => 'navy',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']));

    expect(ShopSettings::current()->fresh()->nexpart_enabled)->toBeTrue()
        ->and(ShopIntegrationCredentials::forCurrentShop()->nexpartLaunchUrl())->toBe('https://nexpart.example');

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.catalog-links.update'), [
            'create' => [
                'label' => "O'Reilly Pro",
                'url' => 'https://professionals.oreillyauto.com',
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'partstech']));

    $links = PartsCatalogLinks::customForShop();
    $oreilly = collect($links)->firstWhere('label', "O'Reilly Pro");

    expect($oreilly['url'])->toBe('https://professionals.oreillyauto.com')
        ->and($oreilly['key'])->toBe('oreilly-pro')
        ->and($oreilly['mode'])->toBe(PartsCatalogLinks::MODE_LINK)
        ->and($oreilly['color'])->toBe('green');

    $catalogs = app(PartsCatalogConnections::class)->forRepairOrder(catalogLinkRepairOrder());
    $nexpart = collect($catalogs)->firstWhere('key', PartsCatalogProvider::Nexpart->value);
    $oreillyButton = collect($catalogs)->firstWhere('key', 'oreilly-pro');

    expect(collect($catalogs)->pluck('key')->all())
        ->toContain(PartsCatalogProvider::Nexpart->value)
        ->toContain('oreilly-pro')
        ->and($nexpart['can_open'])->toBeTrue()
        ->and($nexpart['mode'])->toBe(PartsCatalogLinks::MODE_LINK)
        ->and($nexpart['launch']['windowName'])->toBe('_blank')
        ->and($oreillyButton['can_pull_quote'])->toBeFalse()
        ->and($oreillyButton['mode'])->toBe(PartsCatalogLinks::MODE_LINK)
        ->and($oreillyButton['color'])->toBe('green')
        ->and($oreillyButton['button_class'])->toContain('catalog-green');
});

test('advisor cannot modify shop catalog links', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->patch(route('operations.settings.shop.catalog-links.update'), [
            'create' => [
                'label' => 'AutoZone Pro',
                'url' => 'https://www.autozonepro.com',
            ],
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    expect(PartsCatalogLinks::customForShop())->toBe([]);
});

test('advisor can add a personal catalog link and default on profile', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('profile.edit', ['tab' => 'catalogs']))
        ->assertOk()
        ->assertSee('Default catalog')
        ->assertSee('PartsTech')
        ->assertSee('RepairLink')
        ->assertSee('Nexpart');

    $this->actingAs($advisor)
        ->patch(route('profile.catalogs.update'), [
            'default_parts_catalog' => PartsCatalogProvider::PartsTech->value,
            'create' => [
                'label' => 'First Call',
                'url' => 'https://www.firstcallonline.com',
            ],
        ])
        ->assertRedirect(route('profile.edit', ['tab' => 'catalogs']));

    $advisor->refresh();
    $firstCall = collect(PartsCatalogLinks::customForUser($advisor))->firstWhere('label', 'First Call');

    expect($firstCall['url'])->toBe('https://www.firstcallonline.com')
        ->and($firstCall['mode'])->toBe(PartsCatalogLinks::MODE_LINK)
        ->and($firstCall['color'])->toBe('red')
        ->and($advisor->default_parts_catalog)->toBe(PartsCatalogProvider::PartsTech->value);

    $catalogs = app(PartsCatalogConnections::class)->forRepairOrder(catalogLinkRepairOrder(), $advisor);
    $firstCallButton = collect($catalogs)->firstWhere('key', $firstCall['key']);

    expect(collect($catalogs)->pluck('key'))->toContain($firstCall['key'])
        ->and($firstCallButton['launch']['url'])->toBe('https://www.firstcallonline.com')
        ->and($firstCallButton['launch']['windowName'])->toBe('_blank')
        ->and($firstCallButton['color'])->toBe('red');
});

test('personal catalog links stay off other advisors toolbars', function () {
    $owner = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $other = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    PartsCatalogLinks::persistUser($owner, [
        [
            'key' => 'autozone-pro',
            'label' => 'AutoZone Pro',
            'url' => 'https://www.autozonepro.com',
        ],
    ]);

    $repairOrder = catalogLinkRepairOrder();

    expect(collect(app(PartsCatalogConnections::class)->forRepairOrder($repairOrder, $owner))->pluck('key'))
        ->toContain('autozone-pro')
        ->and(collect(app(PartsCatalogConnections::class)->forRepairOrder($repairOrder, $other))->pluck('key'))
        ->not->toContain('autozone-pro');
});

test('http catalog links are rejected on profile', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->patch(route('profile.catalogs.update'), [
            'create' => [
                'label' => 'AutoZone Pro',
                'url' => 'http://www.autozonepro.com',
            ],
        ])
        ->assertRedirect(route('profile.edit', ['tab' => 'catalogs']))
        ->assertSessionHasErrors('catalog_links');

    expect(PartsCatalogLinks::customForUser($advisor->fresh()))->toBe([]);
});

test('autozone personal links default to orange and can sit as a catalog', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->patch(route('profile.catalogs.update'), [
            'create' => [
                'label' => 'AutoZone Pro',
                'url' => 'https://www.autozonepro.com',
                'mode' => PartsCatalogLinks::MODE_CATALOG,
            ],
        ])
        ->assertRedirect(route('profile.edit', ['tab' => 'catalogs']));

    $autozone = collect(PartsCatalogLinks::customForUser($advisor->fresh()))->firstWhere('label', 'AutoZone Pro');
    $button = collect(app(PartsCatalogConnections::class)->forRepairOrder(catalogLinkRepairOrder(), $advisor->fresh()))
        ->firstWhere('key', $autozone['key']);

    expect($autozone['color'])->toBe('orange')
        ->and($button['mode'])->toBe(PartsCatalogLinks::MODE_CATALOG)
        ->and($button['color'])->toBe('orange')
        ->and($button['launch']['windowName'])->toStartWith('ark-parts-');
});

test('nexpart is a catalog only when the shop pays for platform parts catalog', function () {
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    ShopSettings::current()->persistTrusted([
        'nexpart_enabled' => true,
        'nexpart_url' => 'https://nexpart.example',
    ]);

    $withoutPlatform = collect(app(PartsCatalogConnections::class)->forRepairOrder(catalogLinkRepairOrder()))
        ->firstWhere('key', PartsCatalogProvider::Nexpart->value);

    expect($withoutPlatform['mode'])->toBe(PartsCatalogLinks::MODE_LINK)
        ->and($withoutPlatform['launch']['windowName'])->toBe('_blank');

    enableHostedPlatformParts();

    $withPlatform = collect(app(PartsCatalogConnections::class)->forRepairOrder(catalogLinkRepairOrder()))
        ->firstWhere('key', PartsCatalogProvider::Nexpart->value);

    expect($withPlatform['mode'])->toBe(PartsCatalogLinks::MODE_CATALOG)
        ->and($withPlatform['can_pull_quote'])->toBeFalse()
        ->and($withPlatform['launch']['windowName'])->toStartWith('ark-parts-nexpart');
});

test('partstech falls back to a new-tab link when platform is connected without parts catalog', function () {
    enableHostedPlatformParts();
    config()->set('services.ark_platform.parts_catalog', false);

    $partstech = collect(app(PartsCatalogConnections::class)->forRepairOrder(catalogLinkRepairOrder()))
        ->firstWhere('key', PartsCatalogProvider::PartsTech->value);

    expect($partstech['mode'])->toBe(PartsCatalogLinks::MODE_LINK)
        ->and($partstech['can_pull_quote'])->toBeFalse()
        ->and($partstech['launch']['url'])->toBe('https://partstech.test')
        ->and($partstech['launch']['windowName'])->toBe('_blank')
        ->and($partstech['color'])->toBe('yellow');
});
