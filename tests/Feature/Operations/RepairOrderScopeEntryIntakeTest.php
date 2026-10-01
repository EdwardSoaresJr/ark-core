<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryConcept;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptLearner;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptObservation;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\RepairOrders\ScopeEntryVocabularyQuery;
use App\Ark\Operations\RepairOrders\ScopeRepairActionSuggestionQuery;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('worksheet intake uses a single conversational field without category radios', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('+ Add Work', false)
        ->assertSee('Customer Concern', false)
        ->assertSee('What would you like to add?', false)
        ->assertSee('scope-entry-summary', false)
        ->assertSee('Create Concern', false)
        ->assertDontSee('scope_entry_kind_choice', false)
        ->assertDontSee('ops-worksheet-entry-intake__trigger', false)
        ->assertDontSee('Add scope', false);
});

test('free typed novel text infers entry kind only when no vocabulary match is selected', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => 'I need front brakes',
            'observed_summary' => 'I need front brakes',
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.RepairOrderConcern::query()->latest('id')->value('id'))
        ->assertSessionHas('worksheet_focus_concern_id')
        ->assertSessionHas('worksheet_repair_action_suggestions');

    $concern = RepairOrderConcern::query()->where('summary', 'I need front brakes')->firstOrFail();

    expect($concern->entryKind())->toBe(ScopeEntryKind::CustomerRequested)
        ->and($concern->recommendationIntent())->toBe(RecommendationIntent::Maintenance)
        ->and($concern->customer_states)->toBe('I need front brakes');
});

test('selected vocabulary suggestion preserves entry kind and learns concept alias', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brake service',
            'observed_summary' => 'front brake job',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()->where('summary', 'Front brake service')->firstOrFail();

    expect($concern->entryKind())->toBe(ScopeEntryKind::CustomerRequested)
        ->and($concern->scope_entry_concept_id)->not->toBeNull()
        ->and($concern->customer_states)->toBe('front brake job');

    $concept = ScopeEntryConcept::query()->findOrFail($concern->scope_entry_concept_id);

    expect($concept->canonical_summary)->toBe('Front brake service')
        ->and(ScopeEntryConceptObservation::query()->where('scope_entry_concept_id', $concept->id)->count())->toBeGreaterThan(0);

    expect($concept->projectionsFor(\App\Ark\Operations\RepairOrders\ScopeLanguageAudience::Customer))
        ->toContain('front brake job');
});

test('scope entry vocabulary returns featured shop match and grouped suggestions', function (): void {
    $repairOrder = repairOrderForScopeEntryIntake();

    foreach (range(1, 5) as $i) {
        RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Front brake service',
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
            'position' => $i,
        ]);
    }

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise',
        'scope_entry_kind' => ScopeEntryKind::CustomerConcern,
        'position' => 10,
    ]);

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('bra');

    expect($payload['has_matches'])->toBeTrue()
        ->and($payload['featured']['summary'] ?? null)->toBe('Front brake service')
        ->and(collect($payload['groups'])->pluck('entry_kind')->all())
        ->toContain(ScopeEntryKind::CustomerRequested->value, ScopeEntryKind::CustomerConcern->value);
});

test('concept alias resolves to canonical summary in vocabulary suggest', function (): void {
    $repairOrder = repairOrderForScopeEntryIntake();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake service',
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
        'position' => 1,
    ]);

    app(ScopeEntryConceptLearner::class)->record(
        $concern,
        ScopeEntryKind::CustomerRequested,
        'Front brake service',
        'pads and rotors',
    );

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('pads');

    expect($payload['has_matches'])->toBeTrue()
        ->and(collect($payload['groups'])->flatMap->suggestions->pluck('summary')->all())
        ->toContain('Front brake service');
});

test('vocabulary suggest preserves rear brakes label when canonical concept is brakes', function (): void {
    $repairOrder = repairOrderForScopeEntryIntake();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
        'position' => 1,
    ]);

    app(ScopeEntryConceptLearner::class)->record(
        $concern,
        ScopeEntryKind::CustomerRequested,
        'Brakes',
        'Rear brakes',
    );

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('rear bra');

    expect($payload['has_matches'])->toBeTrue()
        ->and(collect($payload['groups'])->flatMap->suggestions->pluck('summary')->all())
        ->toContain('Rear brakes');
});

test('scope intake keeps rear brakes headline when advisor picks broader brakes vocabulary', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();

    $existingConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
        'position' => 1,
    ]);

    $concept = app(ScopeEntryConceptLearner::class)->record(
        $existingConcern,
        ScopeEntryKind::CustomerRequested,
        'Brakes',
        'rear brakes',
    );

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'scope_entry_concept_id' => $concept->id,
            'summary' => 'Brakes',
            'observed_summary' => 'Rear brakes',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('summary', 'Rear brakes')
        ->firstOrFail();

    expect($concern->entryKind())->toBe(ScopeEntryKind::CustomerRequested)
        ->and($concern->scope_entry_concept_id)->toBe($concept->id);
});

test('scope intake keeps rear brakes headline when stale vocabulary match is bra', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'bra',
            'observed_summary' => 'Rear brakes',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('summary', 'Rear brakes')
        ->firstOrFail();

    expect($concern->entryKind())->toBe(ScopeEntryKind::CustomerRequested);
});

test('new scope intake surfaces starter repair action shortcuts for brake work', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brake service',
            'observed_summary' => 'I need front brakes',
        ])
        ->assertRedirect()
        ->assertSessionHas('worksheet_repair_action_suggestions.titles');

    $concern = RepairOrderConcern::query()->where('summary', 'Front brake service')->firstOrFail();
    $titles = app(ScopeRepairActionSuggestionQuery::class)->forConcern($concern);

    expect($titles)->toContain('Front Brake Pad Replacement', 'Brake Inspection');
});

test('concern summaries longer than 255 characters can be saved', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForScopeEntryIntake();
    $summary = 'Customer wants a full service visit including '.str_repeat('cabin filter, wiper blades, fluid top-off, ', 10).'and a tire rotation before a long trip.';

    expect(strlen($summary))->toBeGreaterThan(255)
        ->and(strlen($summary))->toBeLessThanOrEqual(2000);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'summary' => $summary,
            'observed_summary' => $summary,
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->latest('id')->firstOrFail();

    expect($concern->summary)->toBe($summary);
});

function repairOrderForScopeEntryIntake(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Entry',
        'last_name' => 'Intake',
        'phone' => '555-0101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Walk-in brake service.',
    ]);
}
