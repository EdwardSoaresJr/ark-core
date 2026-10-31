<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\Portal\PortalEstimateAuthorization;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update(['portal_signature_required' => false]);

    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

test('editing the text of an approved line keeps it approved without a new approval', function () {
    [$repairOrder, $concern, $labor] = reauthApprovedRepairOrder();
    $status = $repairOrder->fresh()->status->value;
    $scopeBefore = ApprovedWorkScope::query()->sole()->getAttributes();
    $eventBefore = ApprovalEvent::query()->sole()->getAttributes();

    reauthPatchLabor($repairOrder, $concern, $labor, description: 'Replace front pads and clean slides');

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $authorization = app(WorkCompletionAuthorization::class);

    expect($labor->fresh()->description)->toBe('Replace front pads and clean slides')
        ->and($authorization->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and($authorization->approvedConcernIdsChangedSinceApproval($repairOrder))->toBe([])
        ->and($authorization->customerApprovalCovers($labor->fresh(['concern'])))->toBeTrue()
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($repairOrder->status->value)->toBe($status)
        ->and(ApprovedWorkScope::query()->sole()->getAttributes())->toBe($scopeBefore)
        ->and(ApprovalEvent::query()->sole()->getAttributes())->toBe($eventBefore);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('Needs authorization', false);
});

test('raising the price of an approved line leaves the increase pending until one more approval', function () {
    [$repairOrder, $concern, $labor] = reauthApprovedRepairOrder();
    $status = $repairOrder->fresh()->status->value;
    $authorization = app(WorkCompletionAuthorization::class);
    $approvedSubtotal = $authorization->approvalSubtotalCents($labor->fresh());
    $firstScope = ApprovedWorkScope::query()->sole();
    $firstEvent = ApprovalEvent::query()->sole();
    $firstEventAttributes = $firstEvent->getAttributes();
    $calculator = app(EstimateTotalsCalculator::class);
    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();

    reauthPatchLabor($repairOrder, $concern, $labor, quantity: '1.50');

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $raisedSubtotal = $authorization->approvalSubtotalCents($labor->fresh());

    expect($raisedSubtotal)->toBeGreaterThan($approvedSubtotal)
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore)
        ->and($calculator->totalsForApprovedWork($repairOrder->fresh())->totalCents())->toBe($approvedBefore)
        ->and($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toBe([$labor->id])
        ->and($authorization->approvedSubtotalsBeforeIncrease($repairOrder))->toBe([$labor->id => $approvedSubtotal])
        ->and($authorization->pendingApprovalCents($repairOrder, $labor->fresh()))->toBe($raisedSubtotal - $approvedSubtotal)
        ->and($authorization->customerApprovalCovers($labor->fresh(['concern'])))->toBeTrue()
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($repairOrder->status->value)->toBe($status);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Needs authorization', false)
        ->assertSee('data-approved-line-increase="'.$labor->id.'"', false)
        ->assertSee('Approved sales and the invoice count the approved amount until the customer approves the increase.', false);

    reauthRecordApproval($repairOrder);

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $scopes = ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->orderBy('id')->get();
    $events = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->orderBy('id')->get();

    expect($authorization->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and($scopes)->toHaveCount(2)
        ->and($scopes[0]->getAttributes())->toBe($firstScope->getAttributes())
        ->and($scopes[0]->approvedLines()[$labor->id]['subtotal_cents'])->toBe($approvedSubtotal)
        ->and($scopes[1]->approvedLines()[$labor->id]['subtotal_cents'])->toBe($raisedSubtotal)
        ->and($events)->toHaveCount(2)
        ->and($events[0]->getAttributes())->toBe($firstEventAttributes)
        ->and($events[1]->approved_amount_cents)
        ->toBe(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder)->totalCents())
        ->and($repairOrder->status->value)->toBe($status)
        ->and(reauthDispositionChangesAwayFromApproved($repairOrder))->toBe(0);
});

test('lowering the price of an approved line keeps it approved', function () {
    [$repairOrder, $concern, $labor] = reauthApprovedRepairOrder();

    reauthPatchLabor($repairOrder, $concern, $labor, quantity: '0.50');

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $authorization = app(WorkCompletionAuthorization::class);

    expect($authorization->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and($authorization->approvedConcernIdsChangedSinceApproval($repairOrder))->toBe([]);
});

test('a line added to an approved concern needs approval and one recorded approval covers it', function () {
    [$repairOrder, $concern, $labor, $part] = reauthApprovedRepairOrder();
    $status = $repairOrder->fresh()->status->value;
    $authorization = app(WorkCompletionAuthorization::class);
    $calculator = app(EstimateTotalsCalculator::class);
    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();

    $added = reauthStoreLabor($repairOrder, $concern, 'Replace caliper slide pins', '1.00', '120.00');
    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);

    expect($authorization->customerApprovalCovers($added->fresh(['concern'])))->toBeFalse()
        ->and($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toBe([$added->id])
        ->and($authorization->customerApprovalCovers($labor->fresh(['concern'])))->toBeTrue()
        ->and($authorization->customerApprovalCovers($part->fresh(['concern'])))->toBeTrue()
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe($approvedBefore);

    $presentedDefault = number_format(($approvedBefore + (int) $added->fresh()->total_cents) / 100, 2, '.', '');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Needs authorization', false)
        ->assertSee('Records the customer\'s approval of the work that needs authorization on approved scopes.', false)
        ->assertSee('value="'.$presentedDefault.'"', false);

    reauthRecordApproval($repairOrder, $presentedDefault);

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $approvedAfter = $calculator->approvedTotalsForRead($repairOrder)->totalCents();
    $latestEvent = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->latest('id')->first();

    expect($authorization->customerApprovalCovers($added->fresh(['concern'])))->toBeTrue()
        ->and($authorization->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and($approvedAfter)->toBe($approvedBefore + (int) $added->fresh()->total_cents)
        ->and($latestEvent->approved_amount_cents)->toBe($approvedAfter)
        ->and(ApprovalEvent::query()->where('visit_id', $repairOrder->id)->count())->toBe(2)
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->count())->toBe(2)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($repairOrder->status->value)->toBe($status)
        ->and(reauthDispositionChangesAwayFromApproved($repairOrder))->toBe(0);
});

test('a concern added after approval stays pending and the earlier approval does not cover it', function () {
    [$repairOrder, $concern] = reauthApprovedRepairOrder();
    $firstScope = ApprovedWorkScope::query()->sole()->getAttributes();

    $later = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Coolant leak',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    $laterLine = reauthStoreLabor($repairOrder, $later, 'Pressure test cooling system', '1.00', '95.00');

    reauthRecordApproval($repairOrder);

    expect($later->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and(app(WorkCompletionAuthorization::class)->customerApprovalCovers($laterLine->fresh(['concern'])))->toBeFalse()
        ->and(app(PortalEstimateAuthorization::class)->canAuthorize($repairOrder->fresh()))->toBeTrue();

    EstimateAccessToken::createForPlainToken($repairOrder, reauthPortalToken());

    $this->get(route('portal.estimates.show', ['token' => reauthPortalToken()]))
        ->assertOk()
        ->assertSee('Coolant leak', false)
        ->assertSee('Choose what to approve', false);

    $this->post(route('portal.estimates.authorize', ['token' => reauthPortalToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [$later->id => RepairOrderConcernDisposition::Approved->value],
    ])->assertRedirect();

    expect($later->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(app(WorkCompletionAuthorization::class)->customerApprovalCovers($laterLine->fresh(['concern'])))->toBeTrue()
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->orderBy('id')->first()->getAttributes())->toBe($firstScope);
});

test('removing an approved line keeps the approval history and shows the line as removed', function () {
    [$repairOrder, $concern, $labor, $part] = reauthApprovedRepairOrder();
    $scopeBefore = ApprovedWorkScope::query()->sole()->getAttributes();
    $eventBefore = ApprovalEvent::query()->sole()->getAttributes();
    $calculator = app(EstimateTotalsCalculator::class);
    $authorization = app(WorkCompletionAuthorization::class);
    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();

    $this->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $part]))->assertRedirect();

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);

    expect(RepairOrderLine::query()->whereKey($part->id)->exists())->toBeFalse()
        ->and(ApprovedWorkScope::query()->sole()->getAttributes())->toBe($scopeBefore)
        ->and(ApprovedWorkScope::query()->sole()->lineIds())->toContain($part->id)
        ->and(ApprovalEvent::query()->sole()->getAttributes())->toBe($eventBefore)
        ->and($authorization->approvedLinesRemoved($repairOrder))->toHaveCount(1)
        ->and($authorization->approvedLinesRemoved($repairOrder)[0]['description'])->toBe('Front brake pads')
        ->and($authorization->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and($authorization->customerApprovalCovers($labor->fresh(['concern'])))->toBeTrue()
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBeLessThan($approvedBefore);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Removed after approval', false)
        ->assertSee('Front brake pads', false)
        ->assertDontSee('Needs authorization', false);

    EstimateAccessToken::createForPlainToken($repairOrder, reauthPortalToken());
    auth()->logout();

    $this->get(route('portal.estimates.show', ['token' => reauthPortalToken()]))
        ->assertOk()
        ->assertSee('data-approved-work-removed', false)
        ->assertSee('Removed after your approval', false);

    $this->actingAs($this->advisor);
    reauthRecordApproval($repairOrder);

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $scopes = ApprovedWorkScope::query()->orderBy('id')->get();

    auth()->logout();
    $this->get(route('portal.estimates.show', ['token' => reauthPortalToken()]))
        ->assertOk()
        ->assertDontSee('data-approved-work-removed', false);

    expect($authorization->approvedLinesRemoved($repairOrder))->toBe([])
        ->and($scopes)->toHaveCount(2)
        ->and($scopes[0]->lineIds())->toContain($part->id)
        ->and($scopes[1]->lineIds())->toBe([$labor->id]);
});

test('the customer portal shows changed approved work as waiting for approval', function () {
    [$repairOrder, $concern, $labor] = reauthApprovedRepairOrder();
    EstimateAccessToken::createForPlainToken($repairOrder, reauthPortalToken());

    $this->get(route('portal.estimates.show', ['token' => reauthPortalToken()]))
        ->assertOk()
        ->assertDontSee('data-approved-work-awaiting-approval', false);

    reauthPatchLabor($repairOrder, $concern, $labor, quantity: '2.00');
    reauthStoreLabor($repairOrder, $concern, 'Replace caliper slide pins', '1.00', '120.00');

    expect(app(PortalEstimateAuthorization::class)->presentedWorkIsFullyApproved($repairOrder->fresh()))->toBeFalse();

    auth()->logout();

    $this->get(route('portal.estimates.show', ['token' => reauthPortalToken()]))
        ->assertOk()
        ->assertSee('data-approved-work-awaiting-approval', false)
        ->assertSee('Changes waiting for your approval', false)
        ->assertSee('Replace front pads', false)
        ->assertSee('price increase', false)
        ->assertSee('Replace caliper slide pins', false)
        ->assertDontSee('Work approved</p>', false);

    $this->actingAs($this->advisor);
    reauthRecordApproval($repairOrder);
    auth()->logout();

    $this->get(route('portal.estimates.show', ['token' => reauthPortalToken()]))
        ->assertOk()
        ->assertDontSee('data-approved-work-awaiting-approval', false);
});

test('approvals recorded before line amounts were kept do not flag a price change', function () {
    [$repairOrder, $concern, $labor] = reauthApprovedRepairOrder();

    ApprovedWorkScope::query()->toBase()->update(['approved_lines' => null]);

    reauthPatchLabor($repairOrder, $concern, $labor, quantity: '2.00');

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);

    expect(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and(app(WorkCompletionAuthorization::class)->approvedLinesRemoved($repairOrder))->toBe([]);
});

test('recording approval for added work keeps a repair order in progress', function () {
    [$repairOrder, $concern] = reauthApprovedRepairOrder();
    $repairOrder->update(['status' => RepairOrderStatus::InProgress->value]);

    reauthStoreLabor($repairOrder, $concern, 'Replace caliper slide pins', '1.00', '120.00');
    reauthRecordApproval($repairOrder);

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::InProgress->value)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder->fresh(['concerns', 'lines.concern'])))->toHaveCount(0);
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderLine, 3: RepairOrderLine}
 */
function reauthApprovedRepairOrder(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '555-0188',
        'email' => 'reauth@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Camry',
        'vin' => '4T1BF1FK0GU000001',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Front brakes grinding',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $labor = reauthStoreLabor($repairOrder, $concern, 'Replace front pads', '1.00', '200.00');

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8068,
        'part_cost_cents' => 4000,
        'subtotal_cents' => 8068,
        'total_cents' => 8068,
    ]);

    reauthRecordApproval($repairOrder);

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(ApprovalEvent::query()->where('visit_id', $repairOrder->id)->count())->toBe(1)
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->count())->toBe(1);

    return [$repairOrder->fresh(), $concern->fresh(), $labor->fresh(), $part->fresh()];
}

function reauthStoreLabor(RepairOrder $repairOrder, RepairOrderConcern $concern, string $description, string $quantity, string $unitPrice): RepairOrderLine
{
    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => $description,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ])->assertRedirect();

    return RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', $description)
        ->latest('id')
        ->firstOrFail();
}

function reauthPatchLabor(
    RepairOrder $repairOrder,
    RepairOrderConcern $concern,
    RepairOrderLine $line,
    ?string $description = null,
    ?string $quantity = null,
): void {
    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => $description ?? $line->description,
        'quantity' => $quantity ?? (string) $line->quantity,
        'unit_price' => number_format($line->unit_price_cents / 100, 2, '.', ''),
    ])->assertRedirect();
}

function reauthRecordApproval(RepairOrder $repairOrder, ?string $amount = null): void
{
    test()->post(route('operations.repair-orders.authorization.store', $repairOrder), array_filter([
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Morgan Brown',
        'approved_amount' => $amount,
    ], fn ($value): bool => $value !== null))->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');
}

function reauthDispositionChangesAwayFromApproved(RepairOrder $repairOrder): int
{
    return OperationalEvent::query()
        ->where('aggregate_type', RepairOrder::class)
        ->where('aggregate_id', $repairOrder->id)
        ->where('event_name', OperationalEventName::ConcernDispositionChanged->value)
        ->get()
        ->filter(fn (OperationalEvent $event): bool => ($event->payload_json['prior_disposition'] ?? null) === RepairOrderConcernDisposition::Approved->value)
        ->count();
}

function reauthPortalToken(): string
{
    return str_repeat('r', 64);
}
