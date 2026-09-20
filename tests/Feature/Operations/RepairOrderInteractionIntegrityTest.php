<?php

/**
 * Interaction integrity — not whether code works, whether meaning survives
 * the interaction without interruption.
 *
 * ARK invariant (deeper than "what information should never be lost?"):
 *   Did this preserve meaning, or did it merely preserve data?
 *
 * Automated invariants below cover scenarios that CAN be asserted in CI.
 * Human floor pass (watch keyboard, not faces) covers the rest.
 *
 * Meaning preservation through the shop:
 *   Intake → customer intent    Diagnosis → technician discovery
 *   Estimate → advisor judgment  Approval → customer decision
 *   Production → repair state    Conversation → relationship context
 *   Invoice → completed work     History → entire narrative
 *
 * Three validation states (resolution is future):
 *   Recognition — did ARK understand the words?
 *   Translation — did ARK preserve the meaning?
 *   Resolution  — did the work confirm the meaning?
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  PRODUCTION GATE (before merge)                                     │
 * │                                                                     │
 * │  Did anyone hesitate?              → watch hands, not faces         │
 * │  Did anyone lose momentum?         → msToRepairActionFocus baseline │
 * │  Did ARK preserve the story?       → NarrativeIntegrityTest         │
 * │  Did ARK quietly teach the shop?   → vocabulary convergence (later) │
 * │  Did ARK preserve meaning?         → translationValidation trace    │
 * │                                                                     │
 * │  Two failures today (resolution later):                             │
 * │    Recognition — did ARK understand the words? (novel is OK)        │
 * │    Translation — did ARK preserve the meaning? (diverged is danger) │
 * │                                                                     │
 * │  Notebook (daily floor pass):                                       │
 * │    Recognition / Translation / Resolution (when earned)             │
 * │    Customer / Advisor / Technician — whose meaning was lost?        │
 * │                                                                     │
 * │  Floor pass scenarios (record, don't change yet):                   │
 * │    1. Type "front brakes" — trace in devtools:                      │
 * │       copy(JSON.stringify(window.__arkScopeIntakeLastTrace,null,2)) │
 * │    2. Type "brake" — note: keep typing? arrow? enter? ignore? esc?  │
 * │    3. Novel phrase — ARK should not confidently guess wrong         │
 * │                                                                     │
 * │  Postpone: "Typical workflow" panel — hasn't earned its place yet.  │
 * └─────────────────────────────────────────────────────────────────────┘
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\RepairOrders\ScopeEntryVocabularyQuery;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
});

test('ambiguous brake surfaces multiple matches instead of a single confident guess', function (): void {
    seedInteractionShopVocabulary();

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('brake');

    expect($payload['has_matches'])->toBeTrue()
        ->and($payload['featured']['summary'] ?? null)->not->toBeNull()
        ->and(collect($payload['groups'])->flatMap(fn ($group) => $group['suggestions'])->count())
        ->toBeGreaterThan(1);
});

test('novel phrase has no learned matches and preserves exact customer words', function (): void {
    $repairOrder = interactionRepairOrder();
    $novelPhrase = 'sounds like marbles under the hood';

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest($novelPhrase);

    expect($payload['has_matches'])->toBeFalse();

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => $novelPhrase,
            'observed_summary' => $novelPhrase,
        ])
        ->assertRedirect()
        ->assertSessionHas('worksheet_focus_concern_id');

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();

    expect($concern->summary)->toBe($novelPhrase)
        ->and($concern->customer_states)->toBe($novelPhrase)
        ->and($concern->entryKind())->toBe(ScopeEntryKind::CustomerConcern);
});

test('learned match path preserves observed phrasing separate from canonical selection', function (): void {
    seedInteractionShopVocabulary();
    $repairOrder = interactionRepairOrder();

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brake service',
            'observed_summary' => 'front brakes',
        ]);

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();

    expect($concern->summary)->toBe('Front brake service')
        ->and($concern->customer_states)->toBe('front brakes');
});

test('free text fallback only applies when vocabulary has no matches', function (): void {
    $repairOrder = interactionRepairOrder();

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('completely novel shop phrase xyz');

    expect($payload['has_matches'])->toBeFalse();

    $this->actingAs($this->advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'completely novel shop phrase xyz',
            'observed_summary' => 'completely novel shop phrase xyz',
        ]);

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();

    expect($concern->summary)->toBe('completely novel shop phrase xyz')
        ->and($concern->scope_entry_concept_id)->not->toBeNull();
});

function seedInteractionShopVocabulary(): void
{
    $repairOrder = interactionRepairOrder();

    foreach (range(1, 10) as $i) {
        RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Front brake service',
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
            'position' => $i,
        ]);
    }

    foreach (range(1, 6) as $i) {
        RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Brake noise',
            'scope_entry_kind' => ScopeEntryKind::CustomerConcern,
            'position' => 10 + $i,
        ]);
    }

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'scope_entry_kind' => ScopeEntryKind::Diagnostic,
        'position' => 20,
    ]);
}

function interactionRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Interaction',
        'last_name' => 'Integrity',
        'phone' => '555-0122',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Interaction integrity walk-in.',
    ]);
}
