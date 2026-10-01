<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\PartsMatrixTune\PartsMatrixTuneAssistant;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

test('matrix suggested price from rows matches tier markup authority', function () {
    $calculator = app(EstimateTotalsCalculator::class);

    $rows = [
        ['min_cost' => '0.00', 'max_cost' => '99.99', 'markup_percentage' => '100.00'],
        ['min_cost' => '100.00', 'max_cost' => null, 'markup_percentage' => '50.00'],
    ];

    expect($calculator->matrixSuggestedPriceCentsForRows(2000, $rows))->toBe(4000)
        ->and($calculator->matrixSuggestedPriceCentsForRows(10000, $rows))->toBe(15000);
});

test('parts matrix tune assistant simulates markup changes on closed sample', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'parts_matrices' => [
            [
                'key' => 'tune-test',
                'name' => 'Tune Test',
                'is_default' => true,
                'rows' => [
                    ['min_cost' => '0.00', 'max_cost' => '99999.99', 'markup_percentage' => '100.00', 'sort_order' => 1],
                ],
            ],
        ],
    ]);

    $repairOrder = repairOrderForMatrixTune('Matrix Tune Customer', RepairOrderStatus::Closed);
    $repairOrder->forceFill([
        'opened_at' => matrixTuneOpenedAt(),
        'closed_at' => now(),
        'updated_at' => now(),
    ])->save();

    $concern = concernForMatrixTune($repairOrder);

    foreach ([5000, 6000, 7000, 8000, 9000] as $costCents) {
        partLineForMatrixTune($repairOrder, $concern, $costCents);
    }

    $assistant = app(PartsMatrixTuneAssistant::class);
    [$from, $to] = $assistant->resolveDefaultRange(
        matrixTuneShopToday(),
        matrixTuneShopToday(),
    );

    $baseline = $assistant->analyze($from, $to, 'tune-test');
    $simulated = $assistant->analyze($from, $to, 'tune-test', [0 => '150.00']);

    expect($baseline['insufficient_data'])->toBeFalse()
        ->and($baseline['sample_count'])->toBe(5)
        ->and($baseline['posture']['actual']['margin_percent'])->toBe(50)
        ->and($simulated['simulation']['margin_percent'])->toBeGreaterThan(50)
        ->and($simulated['simulation']['meets_target'])->toBeTrue();
});

test('admin can open parts matrix tune tool and advisor cannot', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($admin)
        ->get(route('operations.owner.parts-matrix-tune'))
        ->assertOk()
        ->assertSee('Matrix tune assistant')
        ->assertSee('Simulate parts matrix changes');

    $this->actingAs($advisor)
        ->get(route('operations.owner.parts-matrix-tune'))
        ->assertForbidden();
});

function matrixTuneShopToday(): string
{
    return OperationalReportDateScope::shopNow()->toDateString();
}

function matrixTuneOpenedAt(): Carbon
{
    return OperationalReportDateScope::shopNow()->copy()->setTime(9, 0)->timezone(config('app.timezone'));
}

function repairOrderForMatrixTune(string $customerName, RepairOrderStatus $status): RepairOrder
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Matrix tune fixture.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function concernForMatrixTune(RepairOrder $repairOrder): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved service',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'billing_posture' => 'default',
        'position' => 1,
    ]);
}

function partLineForMatrixTune(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    int $costCents,
): RepairOrderLine {
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Part line',
        'quantity' => '1.00',
        'unit_price_cents' => $costCents * 2,
        'part_cost_cents' => $costCents,
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'tune-test',
        'subtotal_cents' => $costCents * 2,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => $costCents * 2,
    ]);
}
