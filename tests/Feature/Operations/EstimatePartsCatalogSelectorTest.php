<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\PartsCatalogConnections;
use App\Ark\Operations\Parts\PartsCatalogProvider;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Preferences\EstimateToolbarPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function partsCatalogSelectorRepairOrder(): RepairOrder
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
        'concern_summary' => 'Catalog selector',
    ]);
}

function enablePartsTechCatalog(): void
{
    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');
}

function enableRepairLinkCatalog(string $url = 'https://repairlink.test/catalog'): void
{
    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => true,
        'repairlink_url' => $url,
    ]);
}

test('partstech default selects partstech actions on a new repair order', function () {
    enablePartsTechCatalog();
    enableRepairLinkCatalog();

    $advisor = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::PartsTech->value,
    ])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', partsCatalogSelectorRepairOrder()))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('>PartsTech</span>')
        ->toContain('title="Open PartsTech"')
        ->toContain('>Pull Cart</span>')
        ->toContain('Open RepairLink')
        ->toContain('activatePartsCatalogItem(item)')
        ->toContain('selectPartsCatalog(')
        ->not->toContain('>Nexpart</span>')
        ->not->toContain('>Open RepairLink</span>')
        ->not->toContain('Pull NP Cart');
});

test('repairlink is a new-tab link with no cart pull', function () {
    enablePartsTechCatalog();
    enableRepairLinkCatalog();

    $repairOrder = partsCatalogSelectorRepairOrder();
    $catalogs = app(PartsCatalogConnections::class)->forRepairOrder($repairOrder);
    $repairLink = collect($catalogs)->firstWhere('key', PartsCatalogProvider::RepairLink->value);

    expect($catalogs)->toHaveCount(2)
        ->and($repairLink['can_open'])->toBeTrue()
        ->and($repairLink['can_pull_quote'])->toBeFalse()
        ->and($repairLink['cart_label'])->toBeNull()
        ->and($repairLink['mode'])->toBe('link')
        ->and($repairLink['color'])->toBe('blue')
        ->and($repairLink['open_label'])->toBe('Open RepairLink')
        ->and($repairLink['launch']['url'])->toBe('https://repairlink.test/catalog')
        ->and($repairLink['launch']['windowName'])->toBe('_blank')
        ->and($repairLink['launch']['url'])->not->toContain('?')
        ->and(collect($catalogs)->pluck('key'))->not->toContain(PartsCatalogProvider::Nexpart->value);
});

test('selecting a catalog is a client context change and does not persist', function () {
    enablePartsTechCatalog();
    enableRepairLinkCatalog();

    $advisor = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::PartsTech->value,
    ])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', partsCatalogSelectorRepairOrder()))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('selectPartsCatalog(key)')
        ->toContain('activatePartsCatalogItem(item)')
        ->toContain('this.partsCatalogSelectedKey = key')
        ->and($advisor->fresh()->default_parts_catalog)->toBe(PartsCatalogProvider::PartsTech->value);

    $selectStart = strpos($html, 'selectPartsCatalog(key)');
    $selectEnd = strpos($html, 'laborGuidePrimary()');
    $selectBody = $selectStart !== false && $selectEnd !== false
        ? substr($html, $selectStart, $selectEnd - $selectStart)
        : '';

    expect($selectBody)->not->toContain('estimateToolbarPersistUrl');
});

test('pull quote is gated to the selected partstech catalog', function () {
    enablePartsTechCatalog();
    enableRepairLinkCatalog();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', partsCatalogSelectorRepairOrder()))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain("if (key !== 'partstech' || this.partsCatalogSelectedKey !== 'partstech')")
        ->toContain('x-show="partsCatalogSelected()?.can_pull_quote"')
        ->toContain("window.open(catalogUrl, 'ark-partstech-catalog')");
});

test('making repairlink default does not replace partstech on the main catalog button', function () {
    enablePartsTechCatalog();
    enableRepairLinkCatalog();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $other = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::PartsTech->value,
    ])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);
    completeRequiredLearnFor($other);

    $this->actingAs($advisor)
        ->postJson(route('operations.estimate-toolbar.default'), [
            'kind' => EstimateToolbarPreference::KIND_PARTS,
            'key' => PartsCatalogProvider::RepairLink->value,
        ])
        ->assertOk()
        ->assertJsonPath('key', PartsCatalogProvider::RepairLink->value);

    expect($advisor->fresh()->default_parts_catalog)->toBe(PartsCatalogProvider::RepairLink->value)
        ->and($other->fresh()->default_parts_catalog)->toBe(PartsCatalogProvider::PartsTech->value);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', partsCatalogSelectorRepairOrder()))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('>PartsTech</span>')
        ->toContain('title="Open PartsTech"')
        ->toContain('>Pull Cart</span>')
        ->toContain('activatePartsCatalogItem(item)')
        ->not->toContain('>RepairLink</span>');
});

test('unavailable saved parts catalog falls back without overwriting the preference', function () {
    enablePartsTechCatalog();

    $advisor = User::factory()->create([
        'default_parts_catalog' => PartsCatalogProvider::Nexpart->value,
    ])->assignRole(ArkRole::Advisor->value);

    $available = [PartsCatalogProvider::PartsTech->value];

    expect(EstimateToolbarPreference::resolve($advisor, EstimateToolbarPreference::KIND_PARTS, $available))
        ->toBe(PartsCatalogProvider::PartsTech->value)
        ->and($advisor->fresh()->default_parts_catalog)->toBe(PartsCatalogProvider::Nexpart->value);
});

test('nexpart is omitted until a launch url is saved', function () {
    enablePartsTechCatalog();
    enableRepairLinkCatalog();

    $catalogs = app(PartsCatalogConnections::class)->forRepairOrder(partsCatalogSelectorRepairOrder());

    expect(collect($catalogs)->pluck('key')->all())
        ->toBe([
            PartsCatalogProvider::PartsTech->value,
            PartsCatalogProvider::RepairLink->value,
        ]);
});

test('repairlink launches the configured shop url', function () {
    enableRepairLinkCatalog('https://www.repairlinkshop.com');

    $catalogs = app(PartsCatalogConnections::class)->forRepairOrder(partsCatalogSelectorRepairOrder());
    $repairLink = collect($catalogs)->firstWhere('key', PartsCatalogProvider::RepairLink->value);

    expect($repairLink['can_open'])->toBeTrue()
        ->and($repairLink['can_pull_quote'])->toBeFalse()
        ->and($repairLink['mode'])->toBe('link')
        ->and($repairLink['launch']['url'])->toBe('https://www.repairlinkshop.com')
        ->and($repairLink['launch']['windowName'])->toBe('_blank')
        ->and($repairLink['launch']['url'])->not->toContain('?');
});

test('repairlink stays closed without a configured launch url', function () {
    enablePartsTechCatalog();
    ShopSettings::current()->persistTrusted([
        'repairlink_enabled' => false,
        'repairlink_url' => null,
    ]);

    $catalogs = app(PartsCatalogConnections::class)->forRepairOrder(partsCatalogSelectorRepairOrder());
    $repairLink = collect($catalogs)->firstWhere('key', PartsCatalogProvider::RepairLink->value);

    expect(collect($catalogs)->pluck('key')->all())
        ->toBe([
            PartsCatalogProvider::PartsTech->value,
        ])
        ->and($repairLink)->toBeNull();
});
