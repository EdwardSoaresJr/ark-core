<?php

/**
 * Floor-pass checklist for scope intake before production.
 *
 * Human pass: Molly/Ben at counter for 15 minutes — if nobody pauses on
 * "how do I enter this?", ship it.
 *
 * Automated pass: seeds shop-learned vocabulary, then asserts ranking,
 * customer_states preservation, and repair-action shortcuts for each
 * ambiguous phrase from the advisor checklist.
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptLearner;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\RepairOrders\ScopeEntryVocabularyQuery;
use App\Ark\Operations\RepairOrders\ScopeRepairActionSuggestionQuery;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    seedFloorPassShopVocabulary();
});

test('ambiguous brake ranks front brake service as shop featured match', function (): void {
    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('brake');

    expect($payload['has_matches'])->toBeTrue()
        ->and($payload['featured']['summary'])->toBe('Front brake service')
        ->and($payload['featured']['entry_kind'])->toBe(ScopeEntryKind::CustomerRequested->value)
        ->and(collect($payload['groups'])->flatMap->suggestions->count())->toBeGreaterThan(1);
});

test('floor pass vocabulary ranking', function (string $query, string $expectedFeatured, string $expectedKind): void {
    $payload = app(ScopeEntryVocabularyQuery::class)->suggest($query);

    expect($payload['has_matches'])->toBeTrue()
        ->and($payload['featured']['summary'] ?? null)->toBe($expectedFeatured)
        ->and($payload['featured']['entry_kind'] ?? null)->toBe($expectedKind);
})->with([
    'front brakes' => ['front brakes', 'Front brake service', ScopeEntryKind::CustomerRequested->value],
    'needs brakes' => ['needs brakes', 'Front brake service', ScopeEntryKind::CustomerRequested->value],
    'brake noise' => ['brake noise', 'Brake noise', ScopeEntryKind::CustomerConcern->value],
    'brake inspection' => ['brake inspection', 'Brake inspection', ScopeEntryKind::Diagnostic->value],
    'pads and rotors' => ['pads and rotors', 'Front brake service', ScopeEntryKind::CustomerRequested->value],
    'oil leak' => ['oil leak', 'Oil leak', ScopeEntryKind::CustomerConcern->value],
    'oil change' => ['oil change', 'Oil change', ScopeEntryKind::CustomerRequested->value],
    'check engine light' => ['check engine light', 'Check engine light', ScopeEntryKind::CustomerConcern->value],
    'battery' => ['battery', 'Battery replacement', ScopeEntryKind::CustomerRequested->value],
    'no start' => ['no start', 'No start', ScopeEntryKind::Diagnostic->value],
    'noise' => ['noise', 'Brake noise', ScopeEntryKind::CustomerConcern->value],
    'diagnostic' => ['diagnostic', 'Diagnostic request', ScopeEntryKind::Diagnostic->value],
]);

test('floor pass enter preserves observed customer phrasing in customer_states', function (
    string $typed,
    string $canonical,
    string $entryKind,
): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = floorPassRepairOrder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => $entryKind,
            'summary' => $canonical,
            'observed_summary' => $typed,
        ])
        ->assertRedirect()
        ->assertSessionHas('worksheet_repair_action_suggestions');

    $concern = RepairOrderConcern::query()->where('summary', $canonical)->latest('id')->firstOrFail();

    expect($concern->summary)->toBe($canonical)
        ->and($concern->customer_states)->toBe($typed)
        ->and($concern->entryKind()->value)->toBe($entryKind);
})->with([
    'front brakes → canonical' => ['I need front brakes', 'Front brake service', ScopeEntryKind::CustomerRequested->value],
    'needs brakes → canonical' => ['needs brakes', 'Front brake service', ScopeEntryKind::CustomerRequested->value],
    'pads and rotors → canonical' => ['pads and rotors', 'Front brake service', ScopeEntryKind::CustomerRequested->value],
    'brake noise verbatim' => ['grinding when I stop', 'Brake noise', ScopeEntryKind::CustomerConcern->value],
    'oil leak verbatim' => ['something leaking under the car', 'Oil leak', ScopeEntryKind::CustomerConcern->value],
]);

test('floor pass repair action shortcuts land after enter on brake and oil work', function (
    string $canonical,
    string $entryKind,
    array $expectedShortcuts,
): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = floorPassRepairOrder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => $entryKind,
            'summary' => $canonical,
            'observed_summary' => $canonical,
        ]);

    $concern = RepairOrderConcern::query()->where('summary', $canonical)->latest('id')->firstOrFail();
    $shortcuts = app(ScopeRepairActionSuggestionQuery::class)->forConcern($concern);

    foreach ($expectedShortcuts as $shortcut) {
        expect($shortcuts)->toContain($shortcut);
    }
})->with([
    'front brake service' => [
        'Front brake service',
        ScopeEntryKind::CustomerRequested->value,
        ['Front Brake Pad Replacement', 'Brake Inspection'],
    ],
    'oil change' => [
        'Oil change',
        ScopeEntryKind::CustomerRequested->value,
        ['Engine Oil & Filter Change'],
    ],
    'oil leak concern' => [
        'Oil leak',
        ScopeEntryKind::CustomerConcern->value,
        ['Fluid Leak Diagnosis'],
    ],
]);

test('observed_summary is stored as concept alias separate from canonical summary', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = floorPassRepairOrder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brake service',
            'observed_summary' => 'needs brakes bad',
        ]);

    $concern = RepairOrderConcern::query()->where('summary', 'Front brake service')->latest('id')->firstOrFail();

    expect($concern->summary)->toBe('Front brake service')
        ->and($concern->customer_states)->toBe('needs brakes bad')
        ->and($concern->scope_entry_concept_id)->not->toBeNull();

    $payload = app(ScopeEntryVocabularyQuery::class)->suggest('needs brakes');

    expect($payload['featured']['summary'] ?? null)->toBe('Front brake service');
});

/**
 * Seed shop-learned vocabulary with realistic frequency skew.
 * Front brake service and oil change dominate — like most shops.
 */
function seedFloorPassShopVocabulary(): void
{
    $repairOrder = floorPassRepairOrder();

    $vocabulary = [
        ['Front brake service', ScopeEntryKind::CustomerRequested, 20],
        ['Oil change', ScopeEntryKind::CustomerRequested, 18],
        ['Brake noise', ScopeEntryKind::CustomerConcern, 12],
        ['Oil leak', ScopeEntryKind::CustomerConcern, 10],
        ['Check engine light', ScopeEntryKind::CustomerConcern, 8],
        ['Battery replacement', ScopeEntryKind::CustomerRequested, 7],
        ['No start', ScopeEntryKind::Diagnostic, 6],
        ['Brake inspection', ScopeEntryKind::Diagnostic, 5],
        ['Diagnostic request', ScopeEntryKind::Diagnostic, 9],
        ['Noise', ScopeEntryKind::CustomerConcern, 4],
        ['Electrical diagnosis', ScopeEntryKind::Diagnostic, 3],
    ];

    $position = 0;

    foreach ($vocabulary as [$summary, $kind, $count]) {
        for ($i = 0; $i < $count; $i++) {
            RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $summary,
                'scope_entry_kind' => $kind,
                'position' => ++$position,
            ]);
        }
    }

    $anchor = RepairOrderConcern::query()
        ->where('summary', 'Front brake service')
        ->firstOrFail();

    app(ScopeEntryConceptLearner::class)->record(
        $anchor,
        ScopeEntryKind::CustomerRequested,
        'Front brake service',
        'pads and rotors',
    );

    app(ScopeEntryConceptLearner::class)->record(
        $anchor,
        ScopeEntryKind::CustomerRequested,
        'Front brake service',
        'needs brakes',
    );

    app(ScopeEntryConceptLearner::class)->record(
        $anchor,
        ScopeEntryKind::CustomerRequested,
        'Front brake service',
        'front brakes',
    );
}

function floorPassRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Floor',
        'last_name' => 'Pass',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Floor pass vocabulary seed.',
    ]);
}
