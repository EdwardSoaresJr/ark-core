<?php

use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('estimate snapshot includes visit reason for customer presentation', function () {
    $repairOrder = visitReasonPolishRepairOrder(
        visitReason: "Need new brakes/rotors for front and rear.\nNoise on extreme stops.",
    );

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder);

    expect($snapshot['intake']['visit_reason'])->toBe($repairOrder->visit_reason);
});

test('portal estimate shows visit reason once, then recommended work', function () {
    $visitReason = "Need new brakes/rotors for front and rear.\nNoise on extreme stops.";
    $repairOrder = visitReasonPolishRepairOrder(visitReason: $visitReason, withConcern: true, withIncludesWorkGroup: true);

    ShopSettings::current()->update([
        'recommendation_disclaimer' => 'Approved work is covered by shop warranty unless noted.',
    ]);

    $plainToken = str_repeat('v', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'created_by_user_id' => null,
    ]);

    $response = $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Reason for Visit', false)
        ->assertSee('Customer reported:', false)
        ->assertSee('Need new brakes/rotors for front and rear.', false)
        ->assertDontSee('Preliminary Estimate', false)
        ->assertDontSee('Until we verify your VIN and inspect the vehicle', false)
        ->assertDontSee('Until we inspect the vehicle', false)
        ->assertDontSee('obtain your approval before performing additional work', false)
        ->assertSee('Recommended Work', false)
        ->assertSee('Front Brake Service', false)
        ->assertDontSee('>Includes<', false)
        ->assertDontSee('Includes</p>', false)
        ->assertSee('Important Information', false)
        ->assertSee('Approved work is covered by shop warranty unless noted.', false)
        ->assertDontSee('What brings them in today?', false)
        ->assertDontSee('>Visit<', false);

    $html = $response->getContent();

    $reasonPos = strpos($html, 'Reason for Visit');
    $importantPos = strpos($html, 'Important Information');
    $authorizePos = strpos($html, 'portal-estimate-authorize');

    expect($reasonPos)->not->toBeFalse()
        ->and($importantPos)->toBeGreaterThan($reasonPos)
        ->and($html)->not->toContain('Includes</p>');

    if ($authorizePos !== false) {
        expect($importantPos)->toBeGreaterThan($authorizePos);
    }
});

test('portal estimate does not show preliminary notice when VIN is on record', function () {
    $repairOrder = visitReasonPolishRepairOrder(
        visitReason: 'Brake noise on hard stops.',
        withConcern: true,
        vin: '1HGBH41JXMN109186',
    );

    $plainToken = str_repeat('w', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'created_by_user_id' => null,
    ]);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Reason for Visit', false)
        ->assertDontSee('Preliminary Estimate', false)
        ->assertDontSee('Until we inspect the vehicle', false)
        ->assertDontSee('Until we verify your VIN', false);
});

test('printed estimate pdf omits preliminary notice when VIN is on record', function () {
    $repairOrder = visitReasonPolishRepairOrder(
        visitReason: 'Brake noise on hard stops.',
        withConcern: true,
        vin: '1HGBH41JXMN109186',
    );

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder);

    expect($snapshot['vehicle']['has_vin'])->toBeTrue()
        ->and($snapshot['vehicle']['vin'])->toBe('1HGBH41JXMN109186');

    $html = view('operations.documents.estimates.pdf', [
        'document' => null,
        'snapshot' => $snapshot,
    ])->render();

    expect($html)
        ->toContain('Reason for Visit')
        ->not->toContain('Preliminary Estimate')
        ->not->toContain('Until we inspect the vehicle')
        ->not->toContain('Until we verify your VIN');
});

test('pdf and browser footers place important information after approval', function () {
    $html = view('operations.documents.partials._document-footer', [
        'variant' => 'pdf',
        'snapshot' => [
            'document_type' => 'estimate',
            'totals' => [
                'labor' => '$0.00',
                'parts' => '$0.00',
                'fees' => '$0.00',
                'tax' => '$0.00',
                'total' => '$0.00',
                'labor_cents' => 0,
                'parts_cents' => 0,
                'fees_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => 0,
                'standing_discount_cents' => 0,
            ],
            'document_footer' => [
                'important_information' => ['Parts availability may change.'],
                'authorization' => ['I authorize the work listed.'],
                'approval' => [
                    'status_label' => 'Pending Approval',
                    'show_signature_lines' => true,
                ],
                'total_label' => 'Total',
            ],
        ],
    ])->render();

    $approvalPos = strpos($html, 'Approval Status');
    $importantPos = strpos($html, 'Important Information');

    expect($approvalPos)->not->toBeFalse()
        ->and($importantPos)->not->toBeFalse()
        ->and($importantPos)->toBeGreaterThan($approvalPos);
});

test('advisor worksheet presents visit reason as intake context with add concern', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = visitReasonPolishRepairOrder(
        visitReason: 'Customer says brakes squeal on hard stops.',
        withConcern: true,
    );

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Reason for Visit', false)
        ->assertSee('Customer says brakes squeal on hard stops.', false)
        ->assertSee("task: 'visit-reason'", false)
        ->assertSee('+ Add Work', false)
        ->assertSee('Front Brake Service', false)
        ->assertSee('Recommended Work', false)
        ->assertDontSee('What brings them in today?', false);
});

function visitReasonPolishRepairOrder(
    string $visitReason,
    bool $withConcern = false,
    bool $withIncludesWorkGroup = false,
    ?string $vin = null,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => 'Kyle',
        'last_name' => 'Kight',
        'phone' => '555-2010',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Kia',
        'model' => 'Forte',
        'vin' => $vin,
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'visit_reason' => $visitReason,
        'concern_summary' => '',
        'opened_at' => now(),
    ]);

    if ($withConcern) {
        $concern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Front Brake Service',
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'position' => 1,
        ]);

        if ($withIncludesWorkGroup) {
            $workGroup = RepairOrderWorkGroup::query()->create([
                'repair_order_concern_id' => $concern->id,
                'title' => 'Brake Pads & Rotors, R&R',
                'position' => 1,
            ]);

            RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $workGroup->id,
                'type' => RepairOrderLineType::Labor,
                'description' => 'Brake Pads & Rotors, R&R',
                'quantity' => '2.00',
                'unit_price_cents' => 16500,
                'subtotal_cents' => 33000,
                'total_cents' => 33000,
            ]);
        }
    }

    return $repairOrder->fresh(['customer', 'vehicle', 'concerns.workGroups.lines']);
}
