<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Triggers\OperationalTriggers;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

test('approval aging creates a calm advisor reminder without changing workflow', function () {
    $repairOrder = triggerRepairOrderFixture(status: RepairOrderStatus::WaitingApproval);
    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer viewed estimate but has not answered.',
        'occurred_at' => now()->subHours(3),
    ]);

    $triggers = app(OperationalTriggers::class)->forRepairOrder($repairOrder->fresh());

    expect($triggers->pluck('label')->all())->toContain('Viewed estimate aging')
        ->and($triggers->firstWhere('label', 'Viewed estimate aging')['action'])->toBe('Follow up viewed estimate')
        ->and($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

test('parts and pickup states derive operational reminders without a dedicated workspace panel', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $waitingParts = triggerRepairOrderFixture(status: RepairOrderStatus::WaitingParts, customerName: 'Parts Reminder');
    $waitingParts->concerns()->first()->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    $waitingParts->lines()->first()->update([
        'type' => RepairOrderLineType::Part,
        'part_cost_cents' => 8000,
        'procurement_state' => PartProcurementState::Backordered,
    ]);
    $waitingParts->forceFill(['updated_at' => now()->subHours(5)])->save();

    $readyPickup = triggerRepairOrderFixture(status: RepairOrderStatus::ReadyPickup, customerName: 'Pickup Reminder');

    $triggers = app(OperationalTriggers::class);

    expect($triggers->forRepairOrder($waitingParts->fresh())->pluck('label')->all())
        ->toContain('Parts blocker aging');

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Operational Reminders')
        ->assertDontSee('workflow builder');

    $this->get(route('operations.repair-orders.show', $waitingParts))
        ->assertOk()
        ->assertDontSee('Operational Reminders')
        ->assertDontSee('Parts blocker aging');

    expect($waitingParts->fresh()->status->is(RepairOrderStatus::WaitingParts))->toBeTrue()
        ->and($readyPickup->fresh()->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue();
});

test('estimate documents exclude internal reminders while staff surfaces keep workflow unchanged', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = triggerRepairOrderFixture(status: RepairOrderStatus::WaitingApproval);
    $repairOrder->communicationEvents()->create([
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'summary' => 'Estimate sent by SMS this morning.',
        'occurred_at' => now()->subHours(5),
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->sole();

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Operational Reminders');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('Operational Reminders')
        ->assertDontSee('Approval follow-up due');

    expect($document->snapshot_json)->not->toHaveKey('operational_triggers')
        ->and($document->snapshot_json['totals']['total_cents'])->toBe(15000)
        ->and($document->snapshot_json['concerns'][0]['lines'][0]['description'])->toBe('Inspect brakes');

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('Inspect brakes')
        ->assertSee('$150.00')
        ->assertDontSee('Operational Reminders')
        ->assertDontSee('Approval follow-up due')
        ->assertDontSee('Check customer response before estimate stalls');

    $pdfHtml = view('operations.documents.estimates.pdf', [
        'document' => $document,
        'snapshot' => $document->snapshot_json,
    ])->render();

    expect($pdfHtml)
        ->toContain('Inspect brakes')
        ->toContain('$150.00')
        ->not->toContain('Operational Timeline')
        ->not->toContain('Operational Reminders')
        ->not->toContain('Approval follow-up due')
        ->not->toContain('Check customer response before estimate stalls');
});

function triggerRepairOrderFixture(RepairOrderStatus $status, string $customerName = 'Trigger Customer'): RepairOrder
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Customer asks for workflow trigger coverage.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 15000,
    ]);

    return $repairOrder;
}
