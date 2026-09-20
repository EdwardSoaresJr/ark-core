<?php

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Maintenance\AddEngineOilServiceAction;
use App\Ark\Operations\Maintenance\AddExtraOilQuartsAtCostAction;
use App\Ark\Operations\Maintenance\ConfirmEngineOilInstalledAction;
use App\Ark\Operations\Maintenance\EngineOilShopDefaults;
use App\Ark\Operations\Maintenance\MaintenanceService;
use App\Ark\Operations\Maintenance\MaintenanceServiceEvent;
use App\Ark\Operations\Maintenance\MaintenanceWasherState;
use App\Ark\Operations\Maintenance\OilChangeStickerGate;
use App\Ark\Operations\Maintenance\ResolveEngineOilPreparedAction;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

test('package line adds to total without contaminating labor rollup', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $repairOrder = repairOrderForEstimateWorkspace();
    $result = app(AddEngineOilServiceAction::class)->handle($repairOrder, $admin);
    $service = $result['service'];
    $line = $service->packageLine;

    expect($result['created'])->toBeTrue()
        ->and($line?->type)->toBe(RepairOrderLineType::Package)
        ->and($line?->type->countsTowardLaborRollup())->toBeFalse()
        ->and($line?->type->isPackage())->toBeTrue();

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    expect($totals->grossPackageCents())->toBe(EngineOilShopDefaults::DEFAULT_PACKAGE_PRICE_CENTS)
        ->and($totals->grossLaborCents())->toBe(0)
        ->and($totals->totalCents())->toBeGreaterThanOrEqual(EngineOilShopDefaults::DEFAULT_PACKAGE_PRICE_CENTS);
});

test('add engine oil service is idempotent for one active service per RO', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $repairOrder = repairOrderForEstimateWorkspace();
    $action = app(AddEngineOilServiceAction::class);

    $first = $action->handle($repairOrder, $admin);
    $second = $action->handle($repairOrder->fresh(), $admin);

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and($second['service']->id)->toBe($first['service']->id)
        ->and(MaintenanceService::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and(DB::table('repair_order_lines')->where('type', 'package')->count())->toBe(1);
});

test('first visit prepared leaves viscosity quantity and filter unknown', function () {
    ShopSettings::current()->update([
        'maintenance_engine_oil' => [
            'preferred_oil_brand' => 'Mobil 1 Full Synthetic',
            'include_washer_by_default' => true,
            'package_price_cents' => 8995,
            'included_quart_allowance' => '5.00',
        ],
    ]);

    $repairOrder = repairOrderForEstimateWorkspace();
    $prepared = app(ResolveEngineOilPreparedAction::class)->handle((int) $repairOrder->vehicle_id);

    expect($prepared['source'])->toBe('shop_defaults')
        ->and($prepared['prepared_oil_brand'])->toBe('Mobil 1 Full Synthetic')
        ->and($prepared['prepared_viscosity'])->toBeNull()
        ->and($prepared['prepared_quantity_qt'])->toBeNull()
        ->and($prepared['prepared_filter_part'])->toBeNull()
        ->and($prepared['prepared_washer'])->toBe(MaintenanceWasherState::Include);
});

test('confirm installed creates append-only event; sticker print works before and after', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $repairOrder = repairOrderForEstimateWorkspace();
    $repairOrder->update(['vehicle_mileage_in' => 148220]);

    $service = app(AddEngineOilServiceAction::class)->handle($repairOrder, $admin)['service'];

    expect(OilChangeStickerGate::canPrint($repairOrder))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('operations.repair-orders.print-oil-change-sticker', $repairOrder))
        ->assertOk();

    $event = app(ConfirmEngineOilInstalledAction::class)->handle($service, $admin, [
        'oil_brand' => 'Mobil 1 EP',
        'viscosity' => '5W-30',
        'quantity_qt' => '5.5',
        'filter_part' => 'WIX 57035',
        'washer' => MaintenanceWasherState::Installed->value,
        'service_mileage' => 148220,
        'reset_reminder' => true,
    ]);

    expect($event->service_sequence)->toBe(1)
        ->and($event->revision)->toBe(0)
        ->and($event->service_mileage)->toBe(148220)
        ->and(OilChangeStickerGate::canPrint($repairOrder->fresh()))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('operations.repair-orders.print-oil-change-sticker', $repairOrder))
        ->assertOk();
});

test('correction supersedes same service_sequence and auto detect prefers current event', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $repairOrder = repairOrderForEstimateWorkspace();
    $service = app(AddEngineOilServiceAction::class)->handle($repairOrder, $admin)['service'];

    $first = app(ConfirmEngineOilInstalledAction::class)->handle($service, $admin, [
        'oil_brand' => 'Mobil 1 EP',
        'viscosity' => '5W-30',
        'quantity_qt' => '5.5',
        'filter_part' => 'WIX 57035',
        'washer' => MaintenanceWasherState::Installed->value,
        'service_mileage' => 100000,
    ]);

    $second = app(ConfirmEngineOilInstalledAction::class)->handle($service->fresh(), $admin, [
        'oil_brand' => 'Mobil 1 ESP',
        'viscosity' => '0W-20',
        'quantity_qt' => '5.7',
        'filter_part' => 'WIX 57035',
        'washer' => MaintenanceWasherState::Installed->value,
        'service_mileage' => 100000,
        'supersede_event_id' => $first->id,
    ]);

    expect($second->service_sequence)->toBe(1)
        ->and($second->revision)->toBe(1)
        ->and($first->fresh()->superseded_by_event_id)->toBe($second->id)
        ->and(MaintenanceServiceEvent::query()->current()->count())->toBe(1);

    $prepared = app(ResolveEngineOilPreparedAction::class)->handle((int) $repairOrder->vehicle_id);

    expect($prepared['source'])->toBe('prior_event')
        ->and($prepared['prepared_oil_brand'])->toBe('Mobil 1 ESP')
        ->and($prepared['prepared_viscosity'])->toBe('0W-20')
        ->and((float) $prepared['prepared_quantity_qt'])->toBe(5.7)
        ->and($prepared['prepared_filter_part'])->toBe('WIX 57035');
});

test('package work group allows part lines and extra quarts at cost', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $repairOrder = repairOrderForEstimateWorkspace();
    $service = app(AddEngineOilServiceAction::class)->handle($repairOrder, $admin)['service'];
    $workGroup = $service->workGroup()->with('lines')->firstOrFail();

    expect($workGroup->hasPartsAttachAnchor())->toBeTrue()
        ->and($workGroup->hasLaborAnchor())->toBeFalse()
        ->and($workGroup->allowedComposerLineTypes())->toContain(RepairOrderLineType::Part)
        ->and($workGroup->allowedComposerLineTypes())->not->toContain(RepairOrderLineType::Labor);

    $line = app(AddExtraOilQuartsAtCostAction::class)->handle($service, '1.5', '8.50');

    expect($line->type)->toBe(RepairOrderLineType::Part)
        ->and((float) $line->quantity)->toBe(1.5)
        ->and($line->unit_price_cents)->toBe(850)
        ->and($line->part_cost_cents)->toBe(850)
        ->and($line->pricing_mode)->toBe('manual')
        ->and($line->repair_order_work_group_id)->toBe($service->repair_order_work_group_id);

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    expect($totals->grossPackageCents())->toBe(EngineOilShopDefaults::DEFAULT_PACKAGE_PRICE_CENTS)
        ->and($totals->partsCents())->toBeGreaterThan(0);
});

test('add engine oil service recreates after concern orphaned', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $repairOrder = repairOrderForEstimateWorkspace();
    $action = app(AddEngineOilServiceAction::class);

    $first = $action->handle($repairOrder, $admin)['service'];
    $concernId = $first->repair_order_concern_id;
    $lineId = $first->repair_order_line_id;

    \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->whereKey($lineId)->delete();
    \App\Ark\Operations\RepairOrders\RepairOrderConcern::query()->whereKey($concernId)->delete();

    $first->refresh();
    expect($first->isLinkedAlive())->toBeFalse();

    $second = $action->handle($repairOrder->fresh(), $admin);

    expect($second['created'])->toBeTrue()
        ->and($second['service']->id)->not->toBe($first->id)
        ->and($first->fresh()->status->value)->toBe('cancelled')
        ->and($second['service']->isLinkedAlive())->toBeTrue();
});
