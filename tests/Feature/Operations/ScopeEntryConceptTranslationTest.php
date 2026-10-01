<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryConcept;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptLearner;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptObservation;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\RepairOrders\ScopeLanguageAudience;
use App\Ark\Operations\Vehicles\Vehicle;

test('operational concept stores audience projections separately', function (): void {
    $concern = conceptTranslationConcern();

    $concept = app(ScopeEntryConceptLearner::class)->record(
        $concern,
        ScopeEntryKind::CustomerRequested,
        'Front brake service',
        'needs brakes',
    );

    expect($concept->advisorProjection())->toBe('Front brake service')
        ->and($concept->projectionsFor(ScopeLanguageAudience::Customer))->toContain('needs brakes')
        ->and($concept->projectionsFor(ScopeLanguageAudience::Advisor))->toContain('Front brake service');

    $customerObservation = ScopeEntryConceptObservation::query()
        ->where('scope_entry_concept_id', $concept->id)
        ->where('audience', ScopeLanguageAudience::Customer->value)
        ->first();

    expect($customerObservation?->observed_summary)->toBe('needs brakes');
});

test('same customer and advisor wording records one customer projection', function (): void {
    $concern = conceptTranslationConcern();

    $concept = app(ScopeEntryConceptLearner::class)->record(
        $concern,
        ScopeEntryKind::CustomerRequested,
        'Oil change',
        'Oil change',
    );

    expect(
        ScopeEntryConceptObservation::query()
            ->where('scope_entry_concept_id', $concept->id)
            ->count(),
    )->toBe(1)
        ->and(ScopeEntryConcept::query()->find($concept->id)?->advisorProjection())->toBe('Oil change');
});

test('long diagnostic writeups with slash expansions do not overflow observation columns', function (): void {
    $concern = conceptTranslationConcern();

    $writeup = 'Technician confirmed rear differential fluid leak. Differential fluid is burnt and contains visible metal contamination. Internal inspection found heat damage/burning to the ring and pinion gear teeth. Findings confirm internal mechanical failure of the rear differential. Due to internal gear damage and metal contamination, replacement of the rear differential assembly is required.';

    $concept = app(ScopeEntryConceptLearner::class)->record(
        $concern,
        ScopeEntryKind::CustomerRequested,
        $writeup,
        $writeup,
    );

    $observation = ScopeEntryConceptObservation::query()
        ->where('scope_entry_concept_id', $concept->id)
        ->firstOrFail();

    expect(mb_strlen($observation->normalized_summary))->toBeLessThanOrEqual(255)
        ->and(mb_strlen($observation->observed_summary))->toBeLessThanOrEqual(255)
        ->and($observation->normalized_summary)->toContain('heat damage and burning');
});

function conceptTranslationConcern(): RepairOrderConcern
{
    $customer = Customer::query()->create([
        'first_name' => 'Concept',
        'last_name' => 'Translation',
        'phone' => '555-0133',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Concept translation test.',
    ]);

    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Placeholder',
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
        'position' => 1,
    ]);
}
