<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemCategory;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Recommendations\AddRecommendationToEstimateAction;
use App\Ark\Operations\Recommendations\CreateRecommendationAction;
use App\Ark\Operations\Recommendations\CreateRecommendationFromInspectionItemAction;
use App\Ark\Operations\Recommendations\DismissRecommendationAction;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationDecisionReason;
use App\Ark\Operations\Recommendations\RecommendationDueKind;
use App\Ark\Operations\Recommendations\RecommendationEventType;
use App\Ark\Operations\Recommendations\RecommendationLifecycle;
use App\Ark\Operations\Recommendations\RecommendationResolvedReason;
use App\Ark\Operations\Recommendations\RecommendationSourceKind;
use App\Ark\Operations\Recommendations\RecommendationUrgency;
use App\Ark\Operations\Recommendations\RecordRecommendationDecisionAction;
use App\Ark\Operations\Recommendations\RecordRecommendationPresentationAction;
use App\Ark\Operations\Recommendations\ResolveRecommendationAction;
use App\Ark\Operations\Recommendations\SetRecommendationFollowUpAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairActionStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function recommendationFixture(array $overrides = []): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0142',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Honda',
        'model' => 'Pilot',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 128442,
        'concern_summary' => 'Brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front Brake Pads & Rotors',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace pads and rotors',
        'quantity' => '1.00',
        'unit_price_cents' => 68422,
        'subtotal_cents' => 68422,
        'total_cents' => 68422,
    ]);

    return [$customer, $vehicle, $repairOrder, $concern, ...$overrides];
}

test('create recommendation belongs to customer and vehicle with source lineage', function () {
    [$customer, $vehicle, $repairOrder, $concern] = recommendationFixture();

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Front Brake Pads & Rotors',
        'originating_repair_order' => $repairOrder,
        'originating_concern' => $concern,
        'discovered_mileage' => 128442,
        'urgency' => RecommendationUrgency::Now,
        'safety_related' => true,
        'due_kind' => RecommendationDueKind::Now,
        'source_kind' => RecommendationSourceKind::Estimate,
    ]);

    expect($recommendation->customer_id)->toBe($customer->id)
        ->and($recommendation->vehicle_id)->toBe($vehicle->id)
        ->and($recommendation->originating_repair_order_id)->toBe($repairOrder->id)
        ->and($recommendation->originating_repair_order_concern_id)->toBe($concern->id)
        ->and($recommendation->lifecycle)->toBe(RecommendationLifecycle::Open)
        ->and($recommendation->discovered_mileage)->toBe(128442)
        ->and($recommendation->safety_related)->toBeTrue()
        ->and($recommendation->dueIsNow())->toBeTrue();
});

test('recommendation due date mileage and now stay separate from follow-up', function () {
    [$customer, $vehicle] = recommendationFixture();

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Timing belt',
        'due_kind' => RecommendationDueKind::DateOrMileage,
        'due_on' => now()->addDays(90)->toDateString(),
        'due_mileage' => 105000,
        'discovered_mileage' => 102800,
    ]);

    app(SetRecommendationFollowUpAction::class)->schedule($recommendation, now()->addDays(60));

    $recommendation->refresh();

    expect($recommendation->isServiceDue(102800))->toBeFalse()
        ->and($recommendation->isServiceDue(105000))->toBeTrue()
        ->and($recommendation->followUpIsDue())->toBeFalse()
        ->and($recommendation->follow_up_at)->not->toBeNull()
        ->and($recommendation->due_kind)->toBe(RecommendationDueKind::DateOrMileage);

    $nowDue = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Ball joint',
        'due_kind' => RecommendationDueKind::Now,
        'safety_related' => true,
    ]);

    expect($nowDue->isServiceDue(102800))->toBeTrue()
        ->and($nowDue->followUpIsDue())->toBeFalse();
});

test('presentation decline and defer preserve history across repeats', function () {
    [$customer, $vehicle, $repairOrder] = recommendationFixture();
    $actor = User::factory()->create();

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Front Brake Pads & Rotors',
        'originating_repair_order' => $repairOrder,
    ]);

    expect($recommendation->wasDeclined())->toBeFalse()
        ->and($recommendation->wasPresented())->toBeFalse();

    app(RecordRecommendationPresentationAction::class)->handle($recommendation, $repairOrder, $actor, amountCents: 68422);
    app(RecordRecommendationDecisionAction::class)->handle(
        $recommendation,
        RecommendationEventType::Declined,
        $repairOrder,
        actor: $actor,
        amountCents: 68422,
        reason: RecommendationDecisionReason::Budget,
        presentIfMissing: false,
    );

    app(RecordRecommendationPresentationAction::class)->handle($recommendation, $repairOrder, $actor, amountCents: 70114);
    app(RecordRecommendationDecisionAction::class)->handle(
        $recommendation->fresh(['events', 'estimateLinks']),
        RecommendationEventType::Deferred,
        $repairOrder,
        actor: $actor,
        amountCents: 70114,
        reason: RecommendationDecisionReason::NeedsMoreTime,
        followUpAt: now()->addDays(14),
        presentIfMissing: false,
    );

    $recommendation = $recommendation->fresh(['events']);

    expect($recommendation->events)->toHaveCount(5)
        ->and($recommendation->events->pluck('type')->map->value->all())->toBe([
            'presented',
            'declined',
            'presented',
            'deferred',
            'follow_up_set',
        ])
        ->and($recommendation->events[1]->reason())->toBe(RecommendationDecisionReason::Budget)
        ->and($recommendation->events[1]->amount_cents)->toBe(68422)
        ->and($recommendation->events[3]->amount_cents)->toBe(70114)
        ->and($recommendation->lifecycle)->toBe(RecommendationLifecycle::Open)
        ->and($recommendation->follow_up_at)->not->toBeNull();
});

test('not presented work is not recorded as declined', function () {
    [$customer, $vehicle] = recommendationFixture();

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Cabin filter',
    ]);

    expect($recommendation->wasPresented())->toBeFalse()
        ->and($recommendation->wasDeclined())->toBeFalse()
        ->and($recommendation->events)->toHaveCount(0);
});

test('resolve and dismiss keep the recommendation row', function () {
    [$customer, $vehicle, $repairOrder] = recommendationFixture();
    $actor = User::factory()->create();

    $resolved = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Front brakes',
    ]);

    app(ResolveRecommendationAction::class)->handle(
        $resolved,
        RecommendationResolvedReason::Performed,
        actor: $actor,
        repairOrder: $repairOrder,
        resolvedMileage: 129816,
    );

    expect($resolved->fresh()->lifecycle)->toBe(RecommendationLifecycle::Resolved)
        ->and(Recommendation::query()->find($resolved->id))->not->toBeNull()
        ->and($resolved->fresh()->resolved_mileage)->toBe(129816);

    $dismissed = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Cosmetic ding',
    ]);

    app(DismissRecommendationAction::class)->handle($dismissed, $actor, reason: 'selling_vehicle', note: 'Customer selling');

    expect($dismissed->fresh()->lifecycle)->toBe(RecommendationLifecycle::Dismissed)
        ->and(Recommendation::query()->find($dismissed->id))->not->toBeNull();
});

test('inspection finding creates a linked recommendation without becoming the finding', function () {
    [$customer, $vehicle, $repairOrder] = recommendationFixture();
    $tech = User::factory()->create();
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $tech);

    $item = InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => InspectionItemCategory::Steering->value,
        'label' => 'LF outer tie rod',
        'observed_state' => InspectionObservedState::Fail,
        'notes' => '[Safety] LF outer tie rod has excessive play',
        'position' => 1,
    ]);

    $recommendation = app(CreateRecommendationFromInspectionItemAction::class)->handle($item, $repairOrder);
    $again = app(CreateRecommendationFromInspectionItemAction::class)->handle($item, $repairOrder);

    expect($recommendation->originating_inspection_item_id)->toBe($item->id)
        ->and($recommendation->originating_inspection_id)->toBe($inspection->id)
        ->and($recommendation->safety_related)->toBeTrue()
        ->and($recommendation->due_kind)->toBe(RecommendationDueKind::Now)
        ->and($recommendation->source_kind)->toBe(RecommendationSourceKind::Inspection)
        ->and($item->fresh()->label)->toBe('LF outer tie rod')
        ->and($again->id)->toBe($recommendation->id)
        ->and(Recommendation::query()->where('originating_inspection_item_id', $item->id)->count())->toBe(1);
});

test('add to estimate copies current work without mutating the historical repair order', function () {
    [$customer, $vehicle, $historicalRo, $concern] = recommendationFixture();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Front Brake Pads & Rotors',
        'originating_repair_order' => $historicalRo,
        'originating_concern' => $concern,
    ]);

    $nextVisit = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'mileage_in' => 129000,
        'concern_summary' => 'Oil change',
    ]);

    $first = app(AddRecommendationToEstimateAction::class)->handle($recommendation, $nextVisit, $actor);
    $second = app(AddRecommendationToEstimateAction::class)->handle($recommendation->fresh(['estimateLinks.concern']), $nextVisit, $actor);

    $historicalConcern = $concern->fresh(['lines']);
    $newConcern = $first['concern']->fresh(['lines']);

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and($second['concern']->id)->toBe($newConcern->id)
        ->and($historicalConcern->repair_order_id)->toBe($historicalRo->id)
        ->and($historicalConcern->lines)->toHaveCount(1)
        ->and($historicalConcern->lines->first()->total_cents)->toBe(68422)
        ->and($newConcern->repair_order_id)->toBe($nextVisit->id)
        ->and($newConcern->id)->not->toBe($historicalConcern->id)
        ->and($newConcern->lines)->toHaveCount(1)
        ->and($newConcern->lines->first()->total_cents)->toBe(68422)
        ->and($newConcern->lines->first()->id)->not->toBe($historicalConcern->lines->first()->id)
        ->and($recommendation->fresh()->isOnRepairOrder($nextVisit))->toBeTrue();
});

test('add to estimate does not copy declined or deferred visit state onto the new concern', function () {
    [$customer, $vehicle, $historicalRo, $concern] = recommendationFixture();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $concern->update([
        'disposition' => RepairOrderConcernDisposition::Declined,
        'production_status' => ScopeProductionStatus::Completed,
    ]);

    $workGroup = $concern->workGroups()->create([
        'title' => 'Brakes',
        'position' => 1,
        'owner_type' => \App\Ark\Operations\RepairOrders\RepairActionOwnerType::Technician,
        'owner_user_id' => $actor->id,
        'status' => RepairActionStatus::Complete,
    ]);

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Front Brake Pads & Rotors',
        'originating_repair_order' => $historicalRo,
        'originating_concern' => $concern,
    ]);

    $nextVisit = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 129000,
        'concern_summary' => 'Return visit',
    ]);

    $result = app(AddRecommendationToEstimateAction::class)->handle($recommendation, $nextVisit, $actor);

    $historicalConcern = $concern->fresh(['workGroups']);
    $newConcern = $result['concern']->fresh(['workGroups']);

    expect($historicalConcern->disposition)->toBe(RepairOrderConcernDisposition::Declined)
        ->and($historicalConcern->production_status)->toBe(ScopeProductionStatus::Completed)
        ->and($historicalConcern->workGroups->first()?->status)->toBe(RepairActionStatus::Complete)
        ->and($historicalConcern->workGroups->first()?->owner_user_id)->toBe($actor->id)
        ->and($newConcern->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($newConcern->production_status)->toBe(ScopeProductionStatus::Pending)
        ->and($newConcern->workGroups)->toHaveCount(1)
        ->and($newConcern->workGroups->first()?->status)->toBe(RepairActionStatus::Pending)
        ->and($newConcern->workGroups->first()?->owner_user_id)->toBeNull()
        ->and($newConcern->id)->not->toBe($historicalConcern->id);
});

test('resolved recommendation cannot be added to an estimate', function () {
    [$customer, $vehicle, $repairOrder] = recommendationFixture();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Done already',
    ]);

    app(ResolveRecommendationAction::class)->handle($recommendation, RecommendationResolvedReason::Performed, $actor, $repairOrder);

    expect(fn () => app(AddRecommendationToEstimateAction::class)->handle($recommendation->fresh(), $repairOrder, $actor))
        ->toThrow(ValidationException::class);
});

test('recommendations stay isolated to their customer and vehicle', function () {
    [$customer, $vehicle] = recommendationFixture();

    $otherCustomer = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Shop',
        'phone' => '555-0199',
    ]);
    $otherVehicle = Vehicle::query()->create([
        'customer_id' => $otherCustomer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Rosa brakes',
        'safety_related' => true,
    ]);

    expect(Recommendation::query()->where('vehicle_id', $vehicle->id)->count())->toBe(1)
        ->and(Recommendation::query()->where('vehicle_id', $otherVehicle->id)->count())->toBe(0)
        ->and(Recommendation::query()->where('customer_id', $otherCustomer->id)->count())->toBe(0);
});

test('diy core recommendation workflow does not require platform entitlements', function () {
    [$customer, $vehicle, $repairOrder] = recommendationFixture();
    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $recommendation = app(CreateRecommendationAction::class)->handle([
        'customer' => $customer,
        'vehicle' => $vehicle,
        'title' => 'Cabin filter',
        'originating_repair_order' => $repairOrder,
    ]);

    $this->actingAs($actor)
        ->post(route('operations.repair-orders.recommendations.decision', [$repairOrder, $recommendation]), [
            'decision' => 'declined',
            'reason_code' => RecommendationDecisionReason::Budget->value,
        ])
        ->assertRedirect();

    expect($recommendation->fresh(['events'])->wasDeclined())->toBeTrue()
        ->and($recommendation->fresh()->lifecycle)->toBe(RecommendationLifecycle::Open);
});
