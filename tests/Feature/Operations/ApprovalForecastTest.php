<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;


beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function approvalForecastRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Forecast',
        'last_name' => 'Advisor',
        'phone' => '555-0404',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Approval Forecast seed',
    ]);
}

function approvalForecastLaborLine(RepairOrder $repairOrder, RepairOrderConcern $concern, int $totalCents, int $position = 1): void
{
    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor '.$position,
        'quantity' => '1.00',
        'unit_price_cents' => $totalCents,
        'subtotal_cents' => $totalCents,
        'total_cents' => $totalCents,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => $totalCents,
        'position' => $position,
    ]);
}

test('approval forecast projects approved plus pending recommendations', function (): void {
    $repairOrder = approvalForecastRepairOrder();

    $diag = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    approvalForecastLaborLine($repairOrder, $diag, 15000, 1);

    $rec = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    approvalForecastLaborLine($repairOrder, $rec, 208196, 2);

    $forecast = app(ApprovalForecastProjection::class)->for($repairOrder->fresh(['concerns.lines', 'lines.concern']));

    expect($forecast['visible'])->toBeTrue()
        ->and($forecast['approved_cents'])->toBe(15000)
        ->and($forecast['pending_cents'])->toBe(208196)
        ->and($forecast['projected_cents'])->toBe(223196)
        ->and($forecast['pending_concern_count'])->toBe(1);
});

test('approval forecast is hidden when there are no pending recommendations', function (): void {
    $repairOrder = approvalForecastRepairOrder();

    $diag = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    approvalForecastLaborLine($repairOrder, $diag, 15000, 1);

    $forecast = app(ApprovalForecastProjection::class)->for($repairOrder->fresh(['concerns.lines', 'lines.concern']));

    expect($forecast['visible'])->toBeFalse();
});

test('edit estimate shows approval forecast when recommendations exist after approved work', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = approvalForecastRepairOrder();

    $diag = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    approvalForecastLaborLine($repairOrder, $diag, 15000, 1);

    $rec = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    approvalForecastLaborLine($repairOrder, $rec, 84231, 2);

    $this->actingAs($user)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Approval Forecast', false)
        ->assertSee('Approved', false)
        ->assertSee('Needs Approval', false)
        ->assertSee('If Approved', false)
        ->assertDontSee('If All Approved', false)
        ->assertSee('data-approval-forecast', false);
});

test('review estimate shows approval forecast when recommendations exist after approved work', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = approvalForecastRepairOrder();

    $diag = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    approvalForecastLaborLine($repairOrder, $diag, 15000, 1);

    $rec = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    approvalForecastLaborLine($repairOrder, $rec, 84231, 2);

    $this->actingAs($user)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Approval Forecast', false)
        ->assertSee('Approved', false)
        ->assertSee('Needs Approval', false)
        ->assertSee('If Approved', false)
        ->assertDontSee('If All Approved', false)
        ->assertSee('data-approval-forecast', false);
});

test('portal estimate and pdf snapshot surface approval forecast for customers', function (): void {
    $repairOrder = approvalForecastRepairOrder();

    $diag = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    approvalForecastLaborLine($repairOrder, $diag, 15000, 1);

    $rec = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    approvalForecastLaborLine($repairOrder, $rec, 84231, 2);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines', 'lines.concern']));

    expect($snapshot['approval_forecast']['visible'])->toBeTrue()
        ->and($snapshot['approval_forecast']['approved_cents'])->toBe(15000)
        ->and($snapshot['approval_forecast']['pending_cents'])->toBe(84231);

    $html = view('operations.documents.pdf.document', [
        'snapshot' => app(DocumentPdfPresenter::class)->prepareForCustomer($snapshot),
    ])->render();

    expect($html)
        ->toContain('Approved Work')
        ->toContain('Additional Recommendations')
        ->toContain('If This Recommendation Is Approved')
        ->toContain('Approved Work Breakdown')
        ->toContain('totals-row--quiet-final')
        ->not->toContain('Needs your approval')
        ->not->toContain('If you approve')
        ->not->toContain('Approval Forecast')
        ->not->toContain('Conversation prep — not invoice authority');

    $plainToken = str_repeat('f', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'created_by_user_id' => null,
    ]);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Approved Work', false)
        ->assertSee('Additional Recommendations', false)
        ->assertSee('If This Recommendation Is Approved', false)
        ->assertSee('Approved work breakdown', false)
        ->assertSee('Only work you approve will be performed', false)
        ->assertDontSee('Needs your approval', false)
        ->assertDontSee('Approval Forecast', false)
        ->assertDontSee('Conversation prep — not invoice authority', false);
});

test('approval forecast uses plural recommendation wording when multiple recommendations are pending', function (): void {
    $repairOrder = approvalForecastRepairOrder();

    $diag = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);
    approvalForecastLaborLine($repairOrder, $diag, 15000, 1);

    $pads = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    approvalForecastLaborLine($repairOrder, $pads, 40000, 2);

    $rotors = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace rotors',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 3,
    ]);
    approvalForecastLaborLine($repairOrder, $rotors, 50000, 3);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines', 'lines.concern']));

    $html = view('operations.documents.pdf.document', [
        'snapshot' => app(DocumentPdfPresenter::class)->prepareForCustomer($snapshot),
    ])->render();

    expect($html)
        ->toContain('If All Recommendations Are Approved')
        ->not->toContain('If This Recommendation Is Approved');
});
