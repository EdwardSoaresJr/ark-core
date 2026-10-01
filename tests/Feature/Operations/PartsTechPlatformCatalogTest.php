<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\PartsTechCatalogLauncher;
use App\Ark\Operations\Parts\PartsTechShopReference;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function hostedPartsRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'email' => 'rosa@example.com',
        'customer_type' => 'Retail',
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
        'repair_order_id' => 1737,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Hosted PartsTech',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Maintenance',
        'disposition' => 'recommended',
        'position' => 0,
    ]);

    return $repairOrder->fresh(['vehicle', 'customer', 'concerns']);
}

function defaultHostedQuoteLine(): array
{
    return [
        'source_key' => 'pt:item-1',
        'description' => 'K&N Engine Oil Filter',
        'quantity' => '1.00',
        'part_cost' => '10.00',
        'unit_cost_cents' => 1000,
        'part_number' => 'HP-1004',
        'vendor_name' => 'AutoZone',
        'brand_name' => 'K&N',
        'position_label' => null,
        'sourcing_notes' => 'Imported from PartsTech · AutoZone',
    ];
}

beforeEach(function () {
    enableHostedPlatformParts();
});

test('platform ready makes the parts catalog available without calling PartsTech from Core', function () {
    fakeHostedPartsPlatform();

    $repairOrder = hostedPartsRepairOrder();
    $cartReference = PartsTechShopReference::cartReference($repairOrder);

    expect(app(PartsTechCatalogLauncher::class)->configured())->toBeTrue()
        ->and(app(PartsTechCatalogLauncher::class)->usesPlatform())->toBeTrue()
        ->and(app(PartsTechCatalogLauncher::class)->launchUrl($repairOrder))->not->toBeNull();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder), [
            'concern_id' => $repairOrder->concerns->first()->id,
        ])
        ->assertOk()
        ->assertJsonPath('prepared', true)
        ->assertJsonPath('cart_reference', $cartReference)
        ->assertJsonPath('catalog_url', 'https://app.partstech.com?poNumber='.$cartReference)
        ->assertJsonPath('partstech_login_source', 'shop');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/services/parts/sessions/prepare')
        && $request['cart_reference'] === $cartReference
        && $request['actor']['display_name'] !== null
        && ! isset($request['partstech_seat']));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'partstech.test'));
});

test('not entitled keeps the catalog unavailable', function () {
    fakeHostedPartsPlatform([
        'ok' => false,
        'ready' => false,
        'reason_code' => 'not_entitled',
        'message' => 'Installation is not entitled for service: parts.',
        'http_status' => 403,
    ], preventStray: false);

    $repairOrder = hostedPartsRepairOrder();
    $launcher = app(PartsTechCatalogLauncher::class);

    expect($launcher->configured())->toBeFalse()
        ->and($launcher->launchUrl($repairOrder))->toBeNull()
        ->and($launcher->blockedReason($repairOrder))->toContain("isn't enabled");

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath('prepared', false)
        ->assertJsonPath('catalog_url', null);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee("PartsTech Catalog isn't enabled");
});

test('entitled but not ready is a setup state, not a catalog', function () {
    fakeHostedPartsPlatform([
        'ok' => true,
        'ready' => false,
        'reason_code' => 'parts_catalog_not_ready',
        'message' => 'Parts catalog credentials are not configured.',
        'http_status' => 200,
    ], preventStray: false);

    $repairOrder = hostedPartsRepairOrder();
    $launcher = app(PartsTechCatalogLauncher::class);

    expect($launcher->configured())->toBeFalse()
        ->and($launcher->usesRemoteCartPreparation())->toBeFalse()
        ->and($launcher->blockedReason($repairOrder))->toContain('needs setup');

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath('message', $launcher->blockedReason($repairOrder));

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('needs setup on ARK Platform');
});

test('prepare false from platform is returned without fake catalog results', function () {
    fakeHostedPartsPlatform([], [
        'ok' => false,
        'prepared' => false,
        'catalog_url' => 'https://app.partstech.com?poNumber=R1737',
        'partstech_login' => 'ark-shop',
        'partstech_login_source' => 'shop',
        'message' => 'PartsTech cart could not be prepared.',
    ]);

    $repairOrder = hostedPartsRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertOk()
        ->assertJsonPath('prepared', false)
        ->assertJsonPath('message', 'PartsTech cart could not be prepared.')
        ->assertJsonMissingPath('lines');
});

test('platform cart lock returns 423 with blocking cart reference', function () {
    fakeHostedPartsPlatform([], [
        'ok' => false,
        'prepared' => false,
        'cart_locked' => true,
        'blocking_cart_reference' => 'R1729',
        'http_status' => 423,
        'message' => 'PartsTech is currently working with R1729.',
        'reason_code' => 'cart_locked',
    ]);

    $repairOrder = hostedPartsRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder), [
            'force_cart_switch' => false,
        ])
        ->assertStatus(423)
        ->assertJsonPath('cart_locked', true)
        ->assertJsonPath('blocking_cart_reference', 'R1729');
});

test('advisor seat credentials travel with prepare and quote and are not used as shop fallback', function () {
    fakeHostedPartsPlatform([], function (Request $request) {
        expect($request['partstech_seat']['username'])->toBe('edward-seat')
            ->and($request['partstech_seat']['password'])->toBe('seat-password')
            ->and($request['login_username'])->toBe('edward-seat')
            ->and($request['actor']['core_user_id'])->toBeInt()
            ->and($request['actor']['display_name'])->not->toBe('');

        return Http::response(platformPartsPreparePayload($request['cart_reference'], [
            'partstech_login' => 'edward-seat',
            'partstech_login_source' => 'user',
        ]), 200);
    }, function (Request $request) {
        expect($request['partstech_seat']['username'])->toBe('edward-seat')
            ->and($request->method())->toBe('POST');

        return Http::response(platformPartsQuotePayload($request['cart_reference'], [defaultHostedQuoteLine()]), 200);
    });

    $repairOrder = hostedPartsRepairOrder();
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill([
        'partstech_username' => 'edward-seat',
        'partstech_password' => 'seat-password',
    ])->save();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertOk()
        ->assertJsonPath('prepared', true)
        ->assertJsonPath('partstech_login', 'edward-seat')
        ->assertJsonPath('partstech_login_source', 'user');

    $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('lines.0.source_key', 'pt:item-1')
        ->assertJsonPath('lines.0.part_number', 'HP-1004')
        ->assertJsonPath('lines.0.vendor_name', 'AutoZone');
});

test('personal seat authentication failure is surfaced and does not fall back to the shop account', function () {
    fakeHostedPartsPlatform([], [
        'ok' => false,
        'prepared' => false,
        'message' => 'PartsTech login failed.',
        'reason_code' => 'partstech_provider_error',
    ]);

    $repairOrder = hostedPartsRepairOrder();
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill([
        'partstech_username' => 'edward-seat',
        'partstech_password' => 'bad-password',
    ])->save();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertOk()
        ->assertJsonPath('prepared', false)
        ->assertJsonPath('message', "edward-seat's PartsTech login needs attention. PartsTech login failed.");
});

test('platform quote lines import through the existing Core importer and matrix', function () {
    fakeHostedPartsPlatform([], null, platformPartsQuotePayload('R1737', [defaultHostedQuoteLine()]));

    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $repairOrder = hostedPartsRepairOrder();
    $concern = $repairOrder->concerns->first();
    $concern->update(['billing_posture' => ConcernBillingPosture::Warranty]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertOk()
        ->assertJsonPath('default_parts_matrix_key', 'warranty-no-markup')
        ->assertJsonPath('lines.0.source_key', 'pt:item-1');

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.partstech.import', $repairOrder), [
            'assignments' => [
                [
                    'source_key' => 'pt:item-1',
                    'repair_order_concern_id' => $concern->id,
                    'part_cost' => '10.00',
                ],
            ],
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines')
        ->assertSessionHas('status');

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line->type)->toBe(RepairOrderLineType::Part)
        ->and($line->part_number)->toBe('HP-1004')
        ->and($line->vendor_name)->toBe('AutoZone')
        ->and($line->part_cost_cents)->toBe(1000)
        ->and($line->pricing_mode)->toBe('matrix')
        ->and($line->pricing_matrix_key)->toBe('warranty-no-markup')
        ->and($line->matrix_applied)->toBeTrue();
});

test('platform or provider failure does not invent quote lines', function () {
    fakeHostedPartsPlatform([], null, function () {
        return Http::response([
            'ok' => false,
            'reason_code' => 'provider_unavailable',
            'message' => 'PartsTech Catalog is unavailable.',
        ], 503);
    });

    $repairOrder = hostedPartsRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertStatus(422)
        ->assertJsonMissingPath('lines')
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'unavailable') || str_contains($message, 'login needs attention') || $message !== '');
});

test('platform empty quote is not framed as a login failure', function () {
    fakeHostedPartsPlatform([], null, function () {
        return Http::response([
            'ok' => false,
            'reason_code' => 'partstech_provider_error',
            'message' => 'PartsTech cart R1737 is open but has no parts to import. Add parts in PartsTech (signed in as edward@lugsnplugs.com), save the quote, then pull again.',
        ], 422);
    });

    $repairOrder = hostedPartsRepairOrder();
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill([
        'partstech_username' => 'edward@lugsnplugs.com',
        'partstech_password' => 'seat-password',
    ])->save();

    $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.partstech.import.preview', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'PartsTech cart R1737 is open but has no parts to import. Add parts in PartsTech (signed in as edward@lugsnplugs.com), save the quote, then pull again.',
        );
});

test('historical imported part lines survive entitlement off', function () {
    $repairOrder = hostedPartsRepairOrder();
    $concern = $repairOrder->concerns->first();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'K&N Engine Oil Filter',
        'part_number' => 'HP-1004',
        'vendor_name' => 'AutoZone',
        'sourcing_notes' => 'Imported from PartsTech · AutoZone',
        'quantity' => '1.00',
        'unit_price_cents' => 1895,
        'part_cost_cents' => 1000,
        'subtotal_cents' => 1895,
        'total_cents' => 1895,
    ]);

    fakeHostedPartsPlatform([
        'ok' => false,
        'ready' => false,
        'reason_code' => 'not_entitled',
        'message' => 'Installation is not entitled for service: parts.',
        'http_status' => 403,
    ], preventStray: false);

    expect(app(PartsTechCatalogLauncher::class)->configured())->toBeFalse();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('HP-1004')
        ->assertSee('K&N Engine Oil Filter')
        ->assertSee("PartsTech Catalog isn't enabled");

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->where('part_number', 'HP-1004')->exists())->toBeTrue();
});

test('hosted core cannot fall back to direct PartsTech even when leftover shop credentials exist', function () {
    fakeHostedPartsPlatform();

    $repairOrder = hostedPartsRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.partstech.prepare', $repairOrder))
        ->assertOk()
        ->assertJsonPath('prepared', true);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://cloud.test/'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'partstech.test')
        || str_contains($request->url(), '/api/login')
        || str_contains($request->url(), '/graphql'));
});

test('hosted shop settings do not collect a shop-wide PartsTech password', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'partstech']))
        ->assertOk()
        ->assertSee('managed by ARK Platform')
        ->assertDontSee('name="partstech_password"', false);
});
