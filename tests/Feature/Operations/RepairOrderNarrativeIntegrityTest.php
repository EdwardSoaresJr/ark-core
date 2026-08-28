<?php

/**
 * Narrative integrity — not functional intake tests.
 *
 * Question every scenario must answer:
 *   If I reopen this RO in two years, can I still understand what happened?
 *
 * Human floor pass (before production): watch keyboard, not faces.
 *   - Do they stop typing? Arrow? Delete? Ignore suggestions? Escape?
 *   Micro-behaviors reveal whether ARK helps or interrupts.
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptObservation;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
});

test('story 1: customer requested front brakes survives inspection and recommendation without rewriting history', function (): void {
    $repairOrder = narrativeRepairOrder();
    $customerWords = 'I think I need front brakes.';
    $canonical = 'Front brake service';

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => $canonical,
            'observed_summary' => $customerWords,
        ])
        ->assertRedirect();

    $concern = latestConcern($repairOrder);

    assertConcernNarrative($concern, [
        'summary' => $canonical,
        'customer_states' => $customerWords,
        'entry_kind' => ScopeEntryKind::CustomerRequested,
    ]);

    $this->actingAs($this->advisor)
        ->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
            'summary' => $canonical,
            'customer_states' => $customerWords,
            'verified_findings' => 'Front brakes measure 1mm.',
            'recommendation' => 'Replace front brake pads and rotors.',
            'recommendation_intent' => RecommendationIntent::Maintenance->value,
        ])
        ->assertRedirect();

    $concern->refresh();

    assertConcernNarrative($concern, [
        'summary' => $canonical,
        'customer_states' => $customerWords,
        'verified_findings' => 'Front brakes measure 1mm.',
        'recommendation' => 'Replace front brake pads and rotors.',
        'entry_kind' => ScopeEntryKind::CustomerRequested,
    ]);

    assertReopenedWorksheetTellsStory($this, $repairOrder, $concern, [
        $customerWords,
        $canonical,
        'Front brakes measure 1mm.',
        'Replace front brake pads and rotors.',
    ]);
});

test('story 2: radiator request preserved when tech finds water pump leak', function (): void {
    $repairOrder = narrativeRepairOrder();
    $customerWords = 'Replace my radiator.';
    $canonical = 'Radiator replacement';

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => $canonical,
            'observed_summary' => $customerWords,
        ]);

    $concern = latestConcern($repairOrder);

    $this->actingAs($this->advisor)
        ->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
            'summary' => $canonical,
            'customer_states' => $customerWords,
            'verified_findings' => 'Water pump leaking.',
            'recommendation' => 'Replace water pump, thermostat, and flush coolant.',
            'recommendation_intent' => RecommendationIntent::Maintenance->value,
        ]);

    $concern->refresh();

    assertConcernNarrative($concern, [
        'summary' => $canonical,
        'customer_states' => $customerWords,
        'verified_findings' => 'Water pump leaking.',
        'recommendation' => 'Replace water pump, thermostat, and flush coolant.',
        'entry_kind' => ScopeEntryKind::CustomerRequested,
    ]);

    expect($concern->customer_states)->not->toBe($concern->recommendation)
        ->and($concern->summary)->not->toContain('water pump');

    assertReopenedWorksheetTellsStory($this, $repairOrder, $concern, [
        $customerWords,
        $canonical,
        'Water pump leaking.',
        'Replace water pump, thermostat, and flush coolant.',
    ]);
});

test('story 3: customer concern verbatim preserved when advisor selects canonical brake noise', function (): void {
    $repairOrder = narrativeRepairOrder();
    $customerWords = 'Grinding when I stop.';
    $canonical = 'Brake noise';

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
            'summary' => $canonical,
            'observed_summary' => $customerWords,
        ]);

    $concern = latestConcern($repairOrder);

    expect($concern->customer_states)->toBe($customerWords)
        ->and($concern->summary)->toBe($canonical);

    $this->actingAs($this->advisor)
        ->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
            'summary' => $canonical,
            'customer_states' => $customerWords,
            'verified_findings' => 'LF pad backing plate contacting rotor.',
            'recommendation' => 'Replace front brake pads and resurface rotors.',
            'recommendation_intent' => RecommendationIntent::Diagnostic->value,
        ]);

    $concern->refresh();

    assertConcernNarrative($concern, [
        'summary' => $canonical,
        'customer_states' => $customerWords,
        'verified_findings' => 'LF pad backing plate contacting rotor.',
        'recommendation' => 'Replace front brake pads and resurface rotors.',
        'entry_kind' => ScopeEntryKind::CustomerConcern,
    ]);

    expect(ScopeEntryConceptObservation::query()
        ->where('observed_summary', $customerWords)
        ->exists())->toBeTrue();

    assertReopenedWorksheetTellsStory($this, $repairOrder, $concern, [
        $customerWords,
        $canonical,
        'LF pad backing plate contacting rotor.',
    ]);
});

test('reopening ro years later still separates customer language from canonical scope and findings', function (): void {
    $repairOrder = narrativeRepairOrder();

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brake service',
            'observed_summary' => 'needs brakes bad',
        ]);

    $concern = latestConcern($repairOrder);

    $this->actingAs($this->advisor)
        ->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
            'summary' => 'Front brake service',
            'customer_states' => 'needs brakes bad',
            'verified_findings' => 'Front pads at 2mm, rotors glazed.',
            'recommendation' => 'Replace front brake pads and rotors.',
            'recommendation_intent' => RecommendationIntent::Maintenance->value,
        ]);

    $concern->refresh();
    $snapshot = [
        'summary' => $concern->summary,
        'customer_states' => $concern->customer_states,
        'verified_findings' => $concern->verified_findings,
        'recommendation' => $concern->recommendation,
        'entry_kind' => $concern->entryKind()->value,
    ];

    $this->actingAs($this->advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk();

    $concern->refresh();

    expect($concern->summary)->toBe($snapshot['summary'])
        ->and($concern->customer_states)->toBe($snapshot['customer_states'])
        ->and($concern->verified_findings)->toBe($snapshot['verified_findings'])
        ->and($concern->recommendation)->toBe($snapshot['recommendation'])
        ->and($concern->entryKind()->value)->toBe($snapshot['entry_kind']);
});

/**
 * @param  array<string, mixed>  $expected
 */
function assertConcernNarrative(RepairOrderConcern $concern, array $expected): void
{
    if (array_key_exists('summary', $expected)) {
        expect($concern->summary)->toBe($expected['summary']);
    }

    if (array_key_exists('customer_states', $expected)) {
        expect($concern->customer_states)->toBe($expected['customer_states']);
    }

    if (array_key_exists('verified_findings', $expected)) {
        expect($concern->verified_findings)->toBe($expected['verified_findings']);
    }

    if (array_key_exists('recommendation', $expected)) {
        expect($concern->recommendation)->toBe($expected['recommendation']);
    }

    if (array_key_exists('entry_kind', $expected)) {
        expect($concern->entryKind())->toBe($expected['entry_kind']);
    }
}

/**
 * @param  list<string>  $storyFragments
 */
function assertReopenedWorksheetTellsStory(object $testCase, RepairOrder $repairOrder, RepairOrderConcern $concern, array $storyFragments): void
{
    $response = $testCase->actingAs($testCase->advisor)
        ->get(route('operations.repair-orders.show', $repairOrder));

    $response->assertOk();

    foreach ($storyFragments as $fragment) {
        $response->assertSee($fragment, false);
    }

    $response->assertSee('id="concern-'.$concern->id.'"', false);
}

function latestConcern(RepairOrder $repairOrder): RepairOrderConcern
{
    return RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->latest('id')
        ->firstOrFail();
}

function narrativeRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Narrative',
        'last_name' => 'Integrity',
        'phone' => '555-0111',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Narrative integrity walk-in.',
    ]);
}
