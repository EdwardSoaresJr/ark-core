<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\PortalAccessCodeMail;
use Illuminate\Support\Facades\Mail;

/*
| Query budget guardrails — regression ceilings with documented targets.
|
| Surface              | Target | Regression ceiling (today)
| -------------------- | ------ | --------------------------
| Advisor home (/app)  | 75     | 200 (brief + full board totals)
| Workboard (tech)     | 50     | 75 (layout/comms shell; triage projection ~6 RO queries)
| Comms inbox          | 60     | 120 (identity-first list; batch call context)
| RO show              | 35     | 105
| Estimate review      | 35     | 105
| Portal vehicle       | —      | 75
| Customer hub         | —      | 95
|
| RO read-path invariant: GET must not UPDATE repair_order_lines.
*/

beforeEach(function () {
    seedQueryBudgetCatalog();
});

test('advisor home board stays within query budget', function () {
    workboardRepairOrdersForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertOkWithinQueryBudget(route('operations.index'), 200);
});

test('advisor home board get does not update repair order lines', function () {
    workboardRepairOrdersForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetDoesNotUpdateRepairOrderLines(
        fn () => $this->withSession(['ark:front_door_landed' => 'attention'])
            ->get(route('operations.index'))
            ->assertOk(),
    );
});

test('repair order show stays within query budget', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertOkWithinQueryBudget(route('operations.repair-orders.show', $repairOrder), 105);
});

test('repair order estimate review stays within query budget', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertOkWithinQueryBudget(route('operations.repair-orders.show', $repairOrder), 105);
});

test('repair order inspection show hosts the production walk within query budget', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertOkWithinQueryBudget(route('operations.repair-orders.inspection.show', $repairOrder), 160);
});

test('repair order inspect workspace tab stays within query budget', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $url = route('operations.repair-orders.workspace-tabs.show', [
        'repairOrder' => $repairOrder,
        'tab' => 'inspect',
    ]);

    // First visit includes one-time checklist scaffolding (batched inserts).
    assertOkWithinQueryBudget($url, 105);

    // Steady-state revisit must be pure read — and much cheaper.
    $measured = measureQueries(fn () => $this->get($url)->assertOk());

    expect(getMutationQueries($measured['queries']))->toBeEmpty(
        'Revisiting the inspect tab must not mutate operational data.',
    );

    expect($measured['count'])->toBeLessThanOrEqual(
        60,
        sprintf('Expected at most 60 steady-state queries but ran %d.', $measured['count']),
    );
});

test('repair order inspection show get has no mutations', function () {
    $repairOrder = repairOrderForQueryBudget();
    $advisor = actingAsLearnCurrentAdvisor();

    $inspection = app(\App\Ark\Operations\Inspections\EnsureInspectionAction::class)->execute($repairOrder, $advisor);
    \App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog::seedIfMissing();
    app(\App\Ark\Operations\Inspections\ApplyInspectionTemplateAction::class)->execute(
        $repairOrder,
        $inspection,
        actor: $advisor,
    );

    $this->actingAs($advisor);

    assertGetHasNoMutations(
        fn () => $this->get(route('operations.repair-orders.inspection.show', $repairOrder))->assertOk(),
    );
});

test('portal vehicle detail stays within query budget', function () {
    Mail::fake();
    ShopSettings::current()->update(['shop_name' => 'Demo Auto Repair']);

    $customer = portalCustomerForQueryBudget();
    $vehicle = $customer->vehicles()->firstOrFail();

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Query budget active visit',
    ]);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();
    expect($sent)->not->toBeNull();

    $this->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ])->assertRedirect(route('portal.home'));

    queryBudget(
        fn () => $this->get(route('portal.vehicles.show', $vehicle))->assertOk(),
        75,
    );
});

test('customer hub stays within query budget', function () {
    $customer = customerHubCustomerForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertOkWithinQueryBudget(route('operations.customers.show', $customer), 95);
});

test('communications inbox stays within query budget', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->withSession([
            \App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true,
        ]);

    assertOkWithinQueryBudget(
        route('operations.communications.inbox', ['filter' => 'needs']),
        120,
    );
});

test('repair order show get does not update repair order lines', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetDoesNotUpdateRepairOrderLines(
        fn () => $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk(),
    );
});

test('repair order show get has no mutations', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetHasNoMutations(
        fn () => $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk(),
    );
});

test('repair order show get does not mutate call sessions or staff presence', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    $measured = measureQueries(
        fn () => $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk(),
    );

    expect(callSessionMutationQueries($measured['queries']))->toBeEmpty()
        ->and(staffLastSeenMutationQueries($measured['queries']))->toBeEmpty();
});

test('repair order estimate review get does not update repair order lines', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetDoesNotUpdateRepairOrderLines(
        fn () => $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk(),
    );
});

test('repair order estimate review get has no mutations', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetHasNoMutations(
        fn () => $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk(),
    );
});

test('repair order workspace tab get does not update repair order lines', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetDoesNotUpdateRepairOrderLines(
        fn () => $this->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'parts',
        ]))->assertOk(),
    );
});

test('repair order workspace tab get has no mutations', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetHasNoMutations(
        fn () => $this->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'parts',
        ]))->assertOk(),
    );
});

test('repair order comms workspace tab get has no mutations', function () {
    $repairOrder = repairOrderForQueryBudget();

    $this->actingAs(actingAsLearnCurrentAdvisor());

    assertGetHasNoMutations(
        fn () => $this->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))->assertOk(),
    );
});
