<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\WorkTemplates\ApplyWorkTemplateAction;
use App\Ark\Operations\WorkTemplates\HistoricalMatchTier;
use App\Ark\Operations\WorkTemplates\ResolveHistoricalWorkRecall;
use App\Ark\Operations\WorkTemplates\WorkTemplate;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\DB;

function makeRecallTemplate(): WorkTemplate
{
    $template = WorkTemplate::query()->create([
        'title' => 'Front Brake Service',
        'description' => 'Pads and rotors',
        'position' => 1,
    ]);

    $template->lines()->create([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads and rotors',
        'quantity' => '1.50',
        'position' => 1,
    ]);
    $template->lines()->create([
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'position' => 2,
    ]);

    return $template->fresh('lines');
}

/**
 * @param  array{year: int, drivetrain: string, hours: float, disposition?: string, posted?: bool, lost?: bool}  $attrs
 */
function seedHistoricalBrakeJob(WorkTemplate $template, array $attrs): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Hist',
        'last_name' => 'Vehicle '.$attrs['year'].' '.$attrs['drivetrain'].' '.uniqid(),
        'phone' => '555'.random_int(1000, 9999),
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => $attrs['year'],
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'engine' => '3.5L',
        'engine_display' => '3.5L',
        'displacement_liters' => 3.5,
        'drivetrain' => $attrs['drivetrain'],
        'drive' => match ($attrs['drivetrain']) {
            '4wd' => '4WD/4-Wheel Drive/4x4',
            '2wd' => '2WD',
            'fwd' => 'FWD',
            'rwd' => 'RWD',
            'awd' => 'AWD',
            default => (string) $attrs['drivetrain'],
        },
    ]);

    $posted = $attrs['posted'] ?? true;
    $lost = $attrs['lost'] ?? false;

    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $posted ? RepairOrderStatus::Closed : RepairOrderStatus::Estimate,
        'close_variant_key' => $lost ? 'lost' : ($posted ? 'paid' : null),
        'posted_at' => $posted && ! $lost ? now()->subDays(10) : null,
        'closed_at' => $posted ? now()->subDays(10) : null,
        'concern_summary' => 'Front brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Front Brake Service',
        'disposition' => $attrs['disposition'] ?? RepairOrderConcernDisposition::Approved->value,
        'position' => 1,
    ]);

    $workGroup = $concern->workGroups()->create([
        'title' => 'Front Brake Service',
        'position' => 1,
        'created_from_template_id' => $template->id,
    ]);

    $hours = number_format($attrs['hours'], 2, '.', '');
    $ro->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front brake pads and rotors',
        'quantity' => $hours,
        'unit_price_cents' => 16500,
        'labor_entered_hours' => $hours,
        'labor_billed_hours' => $hours,
        'labor_rate_cents' => 16500,
        'subtotal_cents' => (int) round($attrs['hours'] * 16500),
        'matrix_applied' => false,
        'has_core' => false,
        'save_old_part' => false,
        'is_overridden' => false,
        'is_private' => false,
    ]);

    return $ro->fresh('vehicle');
}

function makeCurrentVehicleRo(array $vehicleAttrs): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Current',
        'last_name' => 'Advisor',
        'phone' => '555'.random_int(1000, 9999),
    ]);

    $vehicle = Vehicle::query()->create(array_merge([
        'customer_id' => $customer->id,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'engine' => '3.5L',
        'engine_display' => '3.5L',
        'displacement_liters' => 3.5,
    ], $vehicleAttrs));

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Front brake noise',
    ])->fresh('vehicle');
}

test('exact vehicle configuration prepares median historical labor', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.0]);
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.4]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Exact)
        ->and($recall->sampleCount)->toBe(3)
        ->and($recall->medianHours)->toBe(3.2)
        ->and($recall->minHours)->toBe(3.0)
        ->and($recall->maxHours)->toBe(3.4)
        ->and($recall->preparesLabor)->toBeTrue()
        ->and($recall->previewLaborHours())->toBe(3.2)
        ->and($recall->toArray()['source_label'])->not->toContain('OEM')
        ->and($recall->toArray()['source_label'])->not->toContain('factory')
        ->and($recall->toArray()['source_label'])->not->toContain('book');
});

test('multiple exact histories use median not latest or mean', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 1.5]);
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 1.8]);
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 1.8]);
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 1.9]);
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.5]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->medianHours)->toBe(1.8);
});

test('unknown current drivetrain yields likely match', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);

    $current = makeCurrentVehicleRo([
        'year' => 2017,
        'drivetrain' => null,
        'drive' => null,
    ]);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Likely)
        ->and($recall->preparesLabor)->toBeTrue()
        ->and($recall->tier->requiresReviewBeforeApply())->toBeTrue();
});

test('4wd current vs generic 2wd history is possible and does not prepare labor', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '2wd', 'hours' => 2.4]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Possible)
        ->and($recall->preparesLabor)->toBeFalse()
        ->and($recall->previewLaborHours())->toBe(1.5)
        ->and($recall->medianHours)->toBe(2.4);
});

test('tacoma rwd vs 4wd history is possible', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => 'rwd', 'drive' => 'RWD']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Possible)
        ->and($recall->preparesLabor)->toBeFalse();
});

test('generic 2wd vs rwd is possible without inventing rwd equivalence', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '2wd', 'hours' => 2.4]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => 'rwd', 'drive' => 'RWD']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Possible)
        ->and($recall->preparesLabor)->toBeFalse();
});

test('identical known drivetrains remain exact-eligible', function (string $drive) {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => $drive, 'hours' => 3.2]);

    $current = makeCurrentVehicleRo([
        'year' => 2017,
        'drivetrain' => $drive,
        'drive' => match ($drive) {
            '4wd' => '4WD/4-Wheel Drive/4x4',
            'fwd' => 'FWD',
            'rwd' => 'RWD',
            'awd' => 'AWD',
            default => $drive,
        },
    ]);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Exact)
        ->and($recall->preparesLabor)->toBeTrue();
})->with(['fwd', 'rwd', 'awd', '4wd']);

test('engine difference yields possible match', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);

    $customer = Customer::query()->create(['first_name' => 'A', 'last_name' => 'B', 'phone' => '5551111']);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'engine' => '2.7L',
        'engine_display' => '2.7L',
        'displacement_liters' => 2.7,
        'drivetrain' => '4wd',
        'drive' => '4WD/4-Wheel Drive/4x4',
    ]);
    $current = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Engine differ case',
    ])->fresh('vehicle');

    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Possible)
        ->and($recall->preparesLabor)->toBeFalse();
});

test('no historical match keeps saved work default', function () {
    $template = makeRecallTemplate();
    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::None)
        ->and($recall->previewLaborHours())->toBe(1.5);
});

test('declined and draft history are excluded', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, [
        'year' => 2017,
        'drivetrain' => '4wd',
        'hours' => 9.9,
        'disposition' => RepairOrderConcernDisposition::Declined->value,
    ]);
    seedHistoricalBrakeJob($template, [
        'year' => 2017,
        'drivetrain' => '4wd',
        'hours' => 8.8,
        'posted' => false,
    ]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::None);
});

test('open estimate history is excluded', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, [
        'year' => 2017,
        'drivetrain' => '4wd',
        'hours' => 3.2,
        'posted' => false,
        'disposition' => RepairOrderConcernDisposition::Approved->value,
    ]);

    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::None);
});

test('historical recall preview performs zero writes', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);
    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);

    DB::enableQueryLog();
    $this->actingAs($user)
        ->getJson(route('operations.repair-orders.work-templates.historical-recall', [
            'repairOrder' => $current,
            'workTemplate' => $template,
        ]))
        ->assertOk()
        ->assertJsonPath('recall.tier', 'exact');

    $writes = collect(DB::getQueryLog())->filter(function (array $query): bool {
        $sql = strtolower($query['query']);

        return str_starts_with($sql, 'insert')
            || str_starts_with($sql, 'update')
            || str_starts_with($sql, 'delete');
    });

    expect($writes)->toBeEmpty();
});

test('applying exact historical labor authors ordinary hours and later history edits do not mutate it', function () {
    $actor = User::factory()->create();
    $template = makeRecallTemplate();
    $hist = seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);
    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);

    $result = app(ApplyWorkTemplateAction::class)->handle(
        $current,
        $template,
        $actor,
        null,
        ['hours' => 3.2, 'tier' => 'exact'],
    );

    $labor = $current->lines()->where('type', RepairOrderLineType::Labor->value)->first();
    expect((float) $labor->labor_entered_hours)->toBe(3.2);

    $hist->lines()->where('type', RepairOrderLineType::Labor->value)->update(['quantity' => '9.99', 'labor_entered_hours' => '9.99']);

    expect((float) $labor->fresh()->labor_entered_hours)->toBe(3.2);
});

test('possible match apply ignores historical hours and keeps template default', function () {
    $actor = User::factory()->create();
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '2wd', 'hours' => 2.4]);
    $current = makeCurrentVehicleRo(['year' => 2017, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);

    // Controller would strip possible overrides; action still respects only allowed override.
    $result = app(ApplyWorkTemplateAction::class)->handle($current, $template, $actor);
    $labor = $current->lines()->where('type', RepairOrderLineType::Labor->value)->first();

    expect((float) $labor->quantity)->toBe(1.5);
});

test('nearby year with matching config is likely', function () {
    $template = makeRecallTemplate();
    seedHistoricalBrakeJob($template, ['year' => 2017, 'drivetrain' => '4wd', 'hours' => 3.2]);

    $current = makeCurrentVehicleRo(['year' => 2018, 'drivetrain' => '4wd', 'drive' => '4WD/4-Wheel Drive/4x4']);
    $recall = app(ResolveHistoricalWorkRecall::class)->for($current, $template);

    expect($recall->tier)->toBe(HistoricalMatchTier::Likely);
});
