<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Approvals\RecordCustomerAuthorizationAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Labor\TechnicianFlagRecognition;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\AuthorizationException;
use App\Ark\Operations\RepairOrders\AuthorizationExceptionReason;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RecordAuthorizationExceptionAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use App\Ark\Operations\RepairOrders\UnresolvedAuthorizationReport;
use App\Ark\Operations\RepairOrders\UpdateConcernDispositionAction;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException as AccessDenied;
use Illuminate\Database\QueryException;

const DIAGNOSTIC_EXCEPTION_NOTE = 'Front brake diagnosis only. The repair itself stays recommended until the customer decides.';

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('recommended parts can be ordered and received and cannot be installed', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
            'procurement_state' => PartProcurementState::Received->value,
        ])->assertRedirect();

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Received);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Received)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

test('a part added after approval installs without reauthorization and stays out of approved dollars', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    approveConcern($repairOrder, $concern, $advisor);

    $approvedBefore = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    expect($approvedBefore)->toBeGreaterThan(0);

    $part->update(['procurement_state' => PartProcurementState::Received]);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
        'procurement_state' => PartProcurementState::Installed->value,
    ])->assertRedirect();

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore);

    $added = addPart($repairOrder, $concern, 'Caliper');
    $added->update(['procurement_state' => PartProcurementState::Received]);

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh())->totalCents())->toBe($approvedBefore + 8068);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Caliper', false)
        ->assertSee('Needs authorization', false)
        ->assertSee('On the estimate. Not in approved sales until the customer approves these lines.', false);

    $scopesBefore = ApprovedWorkScope::query()->count();

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertRedirect()
        ->assertSessionMissing('error');

    expect($added->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(ApprovedWorkScope::query()->count())->toBe($scopesBefore)
        ->and(app(WorkCompletionAuthorization::class)->customerApprovalCovers($added->fresh(['concern'])))->toBeFalse()
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Caliper', false)
        ->assertSee('Needs authorization', false);

    approveConcern($repairOrder, $concern->fresh(), $advisor);

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore + 8068);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Caliper', false)
        ->assertDontSee('Needs authorization');
});

test('#66: raising an approved line counts only the approved amount in approved dollars and the invoice', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();

    $approval = app(RecordCustomerAuthorizationAction::class)->execute(
        repairOrder: $repairOrder,
        approvalType: ApprovalType::Repair,
        source: ApprovalSource::Portal,
        approvedBy: 'Paul Howell',
        approvedAmountCents: null,
        notes: null,
        concernDispositions: [$concern->id => RepairOrderConcernDisposition::Approved->value],
        actor: $advisor,
    );

    $calculator = app(EstimateTotalsCalculator::class);
    $approvedAtApproval = $calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    expect($approval->approved_amount_cents)->toBe($approvedAtApproval);

    $part->update(['quantity' => '2.00']);
    $calculator->recalculateRepairOrder($repairOrder->fresh());

    $coverage = app(WorkCompletionAuthorization::class);
    $currentTotal = $calculator->totalsFor($repairOrder->fresh())->totalCents();

    expect($currentTotal)->toBeGreaterThan($approvedAtApproval)
        ->and($calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedAtApproval)
        ->and($calculator->totalsForApprovedWork($repairOrder->fresh())->totalCents())->toBe($approvedAtApproval)
        ->and($approval->fresh()->approved_amount_cents)->toBe($approvedAtApproval)
        ->and($coverage->customerApprovalCovers($part->fresh(['concern'])))->toBeTrue()
        ->and($coverage->linesRequiringAuthorization($repairOrder->fresh())->pluck('id')->all())->toContain($part->id)
        ->and($coverage->workOutsideApprovedSalesCents($repairOrder->fresh()))->toBe($currentTotal - $approvedAtApproval)
        ->and($part->fresh()->quantity)->toEqual('2.00');

    $invoice = app(InvoiceSnapshotBuilder::class)->build($repairOrder->fresh(), $advisor);
    $invoiceLine = collect($invoice['concerns'][0]['lines'])->firstWhere('id', $part->id);

    expect($invoice['totals']['total_cents'])->toBe($approvedAtApproval)
        ->and($invoiceLine['total_cents'])->toBe($approvedAtApproval)
        ->and($invoiceLine['not_approved_cents'])->toBe($currentTotal - $approvedAtApproval);

    app(RecordCustomerAuthorizationAction::class)->execute(
        repairOrder: $repairOrder->fresh(),
        approvalType: ApprovalType::Repair,
        source: ApprovalSource::Phone,
        approvedBy: 'Paul Howell',
        approvedAmountCents: null,
        notes: null,
        concernDispositions: [$concern->id => RepairOrderConcernDisposition::Approved->value],
        actor: $advisor,
    );

    expect($calculator->totalsForApprovedWork($repairOrder->fresh())->totalCents())->toBe($currentTotal)
        ->and($approval->fresh()->approved_amount_cents)->toBe($approvedAtApproval);
});

test('#66: a lowered approved line stays approved at the lower amount', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    approveConcern($repairOrder, $concern, $advisor);

    $part->update(['unit_price_cents' => intdiv((int) $part->unit_price_cents, 2)]);
    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->recalculateRepairOrder($repairOrder->fresh());

    expect($calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents())
        ->toBe($calculator->totalsFor($repairOrder->fresh())->totalCents())
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder->fresh()))->toBeEmpty();
});

test('#66: a raised line keeps exactly the tax and shop fee recorded with its approval', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'default_tax_rate' => '8.000',
        'taxable_parts' => true,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
    ]);
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    approveConcern($repairOrder, $concern, $advisor);
    $authorized = $part->fresh();
    $recorded = ApprovedWorkScope::query()->sole()->approvedLines()[$part->id]['amounts'];

    $part->fresh()->update(['quantity' => '3.00']);
    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    $approved = $calculator->approvedTotalsForRead($repairOrder->fresh());

    expect($recorded['total_cents'])->toBe((int) $authorized->total_cents)
        ->and($recorded['quantity'])->toBe((string) $authorized->quantity)
        ->and((int) $authorized->tax_cents)->toBeGreaterThan(0)
        ->and((int) $authorized->shop_fee_cents)->toBeGreaterThan(0)
        ->and($approved->taxCents())->toBe((int) $authorized->tax_cents)
        ->and($approved->allocatedShopFeesCents())->toBe((int) $authorized->shop_fee_cents)
        ->and($approved->totalCents())->toBe((int) $authorized->total_cents)
        ->and(app(WorkCompletionAuthorization::class)->workOutsideApprovedSalesCents($repairOrder->fresh()))
        ->toBe((int) $part->fresh()->total_cents - (int) $authorized->total_cents);
});

test('#66: a tax or shop fee rise with the subtotal unchanged waits for authorization', function (array $before, array $after) {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'taxable_parts' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
        ...$before,
    ]);
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    approveConcern($repairOrder, $concern, $advisor);
    $authorized = $part->fresh();

    ShopSettings::current()->update($after);
    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    $current = $part->fresh();
    $coverage = app(WorkCompletionAuthorization::class);
    $invoice = app(InvoiceSnapshotBuilder::class)->build($repairOrder->fresh(), $advisor);

    expect((int) $current->subtotal_cents)->toBe((int) $authorized->subtotal_cents)
        ->and((int) $current->total_cents)->toBeGreaterThan((int) $authorized->total_cents)
        ->and($calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe((int) $authorized->total_cents)
        ->and($invoice['totals']['total_cents'])->toBe((int) $authorized->total_cents)
        ->and($coverage->linesRequiringAuthorization($repairOrder->fresh())->pluck('id')->all())->toBe([$part->id])
        ->and($coverage->workOutsideApprovedSalesCents($repairOrder->fresh()))->toBe((int) $current->total_cents - (int) $authorized->total_cents);
})->with([
    'tax rate rises' => [['default_tax_rate' => '5.000', 'shop_fee_enabled' => false], ['default_tax_rate' => '9.000']],
    'shop fee turned on' => [['default_tax_rate' => '5.000', 'shop_fee_enabled' => false], ['shop_fee_enabled' => true]],
]);

test('#66: a legacy line raised, lowered, and raised again stays held to its first ceiling until approval', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    legacyApproval($repairOrder, $concern, $part, $advisor);

    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    expect($part->fresh()->legacy_approved_amounts)->toBeNull();

    $ceilingCents = (int) $part->fresh()->total_cents;
    $approvedFor = fn (): int => $calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    $edit = function (array $attributes) use ($part, $calculator, $repairOrder): void {
        $part->fresh()->update($attributes);
        $calculator->recalculateRepairOrder($repairOrder->fresh());
    };

    $edit(['quantity' => '2.00']);
    expect($approvedFor())->toBe($ceilingCents);

    $edit(['quantity' => '1.00', 'unit_price_cents' => 4000]);
    $lowered = (int) $part->fresh()->total_cents;
    expect($lowered)->toBeLessThan($ceilingCents)
        ->and($approvedFor())->toBe($lowered)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder->fresh()))->toBeEmpty();

    $edit(['quantity' => '4.00']);
    expect((int) $part->fresh()->total_cents)->toBeGreaterThan($ceilingCents)
        ->and($approvedFor())->toBe($ceilingCents)
        ->and($part->fresh()->legacy_approved_amounts['total_cents'])->toBe($ceilingCents)
        ->and(app(WorkCompletionAuthorization::class)->workOutsideApprovedSalesCents($repairOrder->fresh()))
        ->toBe((int) $part->fresh()->total_cents - $ceilingCents);

    approveConcern($repairOrder->fresh(), $concern->fresh(), $advisor);

    expect($approvedFor())->toBe((int) $part->fresh()->total_cents)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder->fresh()))->toBeEmpty();
});

test('#66: raising a line under an approval without amounts holds it to its amounts before the raise', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    legacyApproval($repairOrder, $concern, $part, $advisor);

    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    $beforeRaise = $part->fresh();
    $scope = ApprovedWorkScope::query()->sole();

    $part->fresh()->update(['description' => 'Front brake pads, ceramic']);
    expect($part->fresh()->legacy_approved_amounts)->toBeNull();

    $part->fresh()->update(['quantity' => '2.00']);
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    $part->fresh()->update(['quantity' => '3.00']);
    $calculator->recalculateRepairOrder($repairOrder->fresh());

    $raised = $part->fresh();
    $coverage = app(WorkCompletionAuthorization::class);
    $invoice = app(InvoiceSnapshotBuilder::class)->build($repairOrder->fresh(), $advisor);

    expect($raised->legacy_approved_amounts['total_cents'])->toBe((int) $beforeRaise->total_cents)
        ->and($calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe((int) $beforeRaise->total_cents)
        ->and($invoice['totals']['total_cents'])->toBe((int) $beforeRaise->total_cents)
        ->and($coverage->linesRequiringAuthorization($repairOrder->fresh())->pluck('id')->all())->toContain($part->id)
        ->and($coverage->workOutsideApprovedSalesCents($repairOrder->fresh()))->toBe((int) $raised->total_cents - (int) $beforeRaise->total_cents)
        ->and($coverage->installBlockedReason($raised->fresh(['concern'])))->toBeNull()
        ->and($scope->fresh()->approved_lines)->toBeNull()
        ->and($scope->fresh()->lineIds())->toBe([$part->id]);
});

test('#66: lowering a line under an approval without amounts counts the lower amount and records no ceiling', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    legacyApproval($repairOrder, $concern, $part, $advisor);

    $calculator = app(EstimateTotalsCalculator::class);
    $part->fresh()->update(['unit_price_cents' => 5000]);
    $calculator->recalculateRepairOrder($repairOrder->fresh());

    expect($part->fresh()->legacy_approved_amounts)->toBeNull()
        ->and($calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe((int) $part->fresh()->total_cents)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder->fresh()))->toBeEmpty();
});

test('#66: recording approval brings a raised legacy line in and keeps the old approval on file', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    legacyApproval($repairOrder, $concern, $part, $advisor);
    $legacyScope = ApprovedWorkScope::query()->sole();

    $calculator = app(EstimateTotalsCalculator::class);
    $part->fresh()->update(['quantity' => '2.00']);
    $calculator->recalculateRepairOrder($repairOrder->fresh());
    $raisedCents = (int) $part->fresh()->total_cents;

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.authorization.store', $repairOrder), [
            'source' => ApprovalSource::Phone->value,
            'approved_by' => 'Paul Howell',
            RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)->pendingChangesFingerprint($repairOrder->fresh()),
        ])
        ->assertRedirect();

    $latest = ApprovedWorkScope::query()->latest('id')->first();

    expect(ApprovedWorkScope::query()->count())->toBe(2)
        ->and($legacyScope->fresh()->approved_lines)->toBeNull()
        ->and($latest->approvedLines()[$part->id]['amounts']['total_cents'])->toBe($raisedCents)
        ->and($calculator->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($raisedCents)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder->fresh()))->toBeEmpty();
});

test('labor added after approval does not block completion or a finished status and stays unresolved', function () {
    $advisor = integrityAdvisor();
    allowEstimateToQualityCheck();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    approveConcern($repairOrder, $concern, $advisor);
    $approvedBefore = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    $scopesBefore = ApprovedWorkScope::query()->count();
    $added = addLabor($repairOrder, $concern);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertSessionHasNoErrors();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Completed);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])
        ->assertSessionHasNoErrors();

    $coverage = app(WorkCompletionAuthorization::class);

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(ApprovedWorkScope::query()->count())->toBe($scopesBefore)
        ->and($coverage->lineCovered($added->fresh(['concern'])))->toBeFalse()
        ->and($coverage->linesRequiringAuthorization($repairOrder->fresh())->pluck('id')->all())->toContain($added->id)
        ->and(collect(app(UnresolvedAuthorizationReport::class)->rows())->pluck('line_id')->all())->toContain($added->id)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore);
});

test('a diagnostic exception covers only the selected lines and does not approve the concern', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $labor = addLabor($repairOrder, $concern);
    $this->actingAs($advisor);

    $approvedBefore = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();

    $this->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
        'reason' => 'diagnostic',
        'note' => DIAGNOSTIC_EXCEPTION_NOTE,
        'line_ids' => [$part->id],
    ])->assertRedirect();

    $exception = AuthorizationException::query()->first();
    expect($exception)->not->toBeNull()
        ->and($exception->establishes_customer_consent)->toBeFalse()
        ->and($exception->lineIds())->toBe([$part->id])
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore);

    $event = OperationalEvent::query()->where('event_name', OperationalEventName::AuthorizationExceptionRecorded->value)->first();
    expect($event->payload_json['establishes_customer_consent'])->toBeFalse();

    $part->update(['procurement_state' => PartProcurementState::Received]);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
        'procurement_state' => PartProcurementState::Installed->value,
    ])->assertRedirect();

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Installed);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertSessionHasErrors('production_status');

    $this->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
        'reason' => 'diagnostic',
        'note' => 'Labor for the wobble diagnosis only. The brake repair is still waiting on the customer.',
        'line_ids' => [$labor->id],
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
        'production_status' => ScopeProductionStatus::Completed->value,
    ])->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Completed)
        ->and($concern->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and(TechnicianFlagRecognition::query()->count())->toBe(0);
});

test('a technician cannot record an exception', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    [$repairOrder, $concern, $part] = integrityRepairOrder();

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
            'reason' => 'diagnostic',
            'note' => DIAGNOSTIC_EXCEPTION_NOTE,
            'line_ids' => [$part->id],
        ])->assertForbidden();

    expect(fn () => app(RecordAuthorizationExceptionAction::class)->execute(
        $repairOrder,
        $concern,
        [$part->id],
        AuthorizationExceptionReason::Diagnostic,
        DIAGNOSTIC_EXCEPTION_NOTE,
        $technician,
    ))->toThrow(AccessDenied::class);

    expect(AuthorizationException::query()->count())->toBe(0);
});

test('every exception requires a substantive note and cannot claim customer approval', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
            'reason' => 'diagnostic',
            'note' => 'too short',
            'line_ids' => [$part->id],
        ])
        ->assertSessionHasErrors('note');

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
            'reason' => 'warranty',
            'note' => 'Verbal approval from the customer for the brake job.',
            'line_ids' => [$part->id],
        ])
        ->assertSessionHasErrors('note');

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
            'reason' => 'customer_approved',
            'note' => DIAGNOSTIC_EXCEPTION_NOTE,
            'line_ids' => [$part->id],
        ])
        ->assertSessionHasErrors('reason');

    expect(AuthorizationException::query()->count())->toBe(0)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

test('recommended labor can move to in progress and cannot be marked completed without a basis', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern] = integrityRepairOrder();
    addLabor($repairOrder, $concern);
    $this->actingAs($advisor);

    $this->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
        'production_status' => ScopeProductionStatus::InProgress->value,
    ])->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::InProgress);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertSessionHasErrors('production_status');

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::InProgress);

    $token = $advisor->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id.'/production-status', [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertStatus(422);

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::InProgress);
});

test('customer portal approval covers the lines present and not a line added afterward', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $part->update(['procurement_state' => PartProcurementState::Received]);

    app(RecordCustomerAuthorizationAction::class)->execute(
        repairOrder: $repairOrder,
        approvalType: ApprovalType::Repair,
        source: ApprovalSource::Portal,
        approvedBy: 'Paul Howell',
        approvedAmountCents: null,
        notes: null,
        concernDispositions: [$concern->id => RepairOrderConcernDisposition::Approved->value],
        actor: $advisor,
    );

    expect($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])->assertRedirect();

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Installed);

    $approvedCents = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    $added = addPart($repairOrder, $concern, 'Caliper added after approval');
    $added->update(['procurement_state' => PartProcurementState::Received]);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertSessionMissing('error');

    expect($added->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and(app(WorkCompletionAuthorization::class)->customerApprovalCovers($added->fresh(['concern'])))->toBeFalse()
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedCents);
});

test('warranty billing posture does not authorize installation', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $concern->update(['billing_posture' => ConcernBillingPosture::WarrantyOther]);
    $part->update(['procurement_state' => PartProcurementState::Received]);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertSessionHas('error');

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Received);
});

test('quality check allows open recommended work and blocks installed work that has no basis', function () {
    $advisor = integrityAdvisor();
    allowEstimateToQualityCheck();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::QualityCheck->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value);

    $repairOrder->refresh();
    $repairOrder->update(['status' => RepairOrderStatus::Estimate]);
    $part->update(['procurement_state' => PartProcurementState::Installed]);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])
        ->assertSessionHasErrors('lifecycle');

    $token = $advisor->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])
        ->assertStatus(422);

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::Estimate->value)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
            'reason' => 'diagnostic',
            'note' => DIAGNOSTIC_EXCEPTION_NOTE,
            'line_ids' => [$part->id],
        ])->assertRedirect();

    $this->withToken($token)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])
        ->assertOk();

    expect($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

test('a stored recommended concern with installed parts is reported and not rewritten', function () {
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $repairOrder->update([
        'status' => RepairOrderStatus::QualityCheck,
        'mileage_out' => 120050,
    ]);
    $part->update(['procurement_state' => PartProcurementState::Installed]);

    $updatedAt = $repairOrder->fresh()->updated_at->toJSON();
    $rows = app(UnresolvedAuthorizationReport::class)->rows();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['line_id'])->toBe($part->id)
        ->and($rows->first()['kind'])->toBe('installed_part');

    $repairOrder->refresh();
    $concern->refresh();
    $part->refresh();

    expect($repairOrder->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and($repairOrder->updated_at->toJSON())->toBe($updatedAt)
        ->and($concern->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($part->procurement_state)->toBe(PartProcurementState::Installed)
        ->and(AuthorizationException::query()->count())->toBe(0)
        ->and(ApprovedWorkScope::query()->count())->toBe(0);

    $this->artisan('ark:authorization-integrity')
        ->assertSuccessful()
        ->expectsOutputToContain((string) $repairOrder->repair_order_id)
        ->expectsOutputToContain('Nothing on these repair orders was changed.');
});

test('partial approval installs the approved concern and added work, and leaves the other concern recommended', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $approvedConcern, $approvedPart] = integrityRepairOrder();
    $openConcern = addConcern($repairOrder, 'Rear brakes');
    $openPart = addPart($repairOrder, $openConcern, 'Rear pads');
    $approvedPart->update(['procurement_state' => PartProcurementState::Received]);
    $openPart->update(['procurement_state' => PartProcurementState::Received]);

    app(RecordCustomerAuthorizationAction::class)->execute(
        repairOrder: $repairOrder,
        approvalType: ApprovalType::Partial,
        source: ApprovalSource::Portal,
        approvedBy: 'Paul Howell',
        approvedAmountCents: null,
        notes: null,
        concernDispositions: [
            $approvedConcern->id => RepairOrderConcernDisposition::Approved->value,
            $openConcern->id => RepairOrderConcernDisposition::Recommended->value,
        ],
        actor: $advisor,
    );

    $approvedCents = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    expect($approvedCents)->toBe(8068);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $approvedPart]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])->assertRedirect();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $openPart]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertSessionHas('error');

    $added = addPart($repairOrder, $approvedConcern, 'Hardware added after partial approval');
    $added->update(['procurement_state' => PartProcurementState::Received]);
    $approvedAfterAdd = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    expect($approvedAfterAdd)->toBe($approvedCents)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh())->totalCents())->toBe($approvedCents + 8068);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertSessionMissing('error');

    $repairOrder->update(['status' => RepairOrderStatus::InProgress]);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])->assertRedirect();

    expect($approvedPart->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($openPart->fresh()->procurement_state)->toBe(PartProcurementState::Received)
        ->and($added->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($approvedConcern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($openConcern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedAfterAdd);
});

test('an existing approved concern with no scope row can still be installed and checked', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    $repairOrder->update(['status' => RepairOrderStatus::InProgress]);
    $part->update(['procurement_state' => PartProcurementState::Received]);

    expect(ApprovedWorkScope::query()->count())->toBe(0);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $part]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])->assertRedirect();

    expect($part->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($repairOrder->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and(ApprovedWorkScope::query()->count())->toBe(0)
        ->and(AuthorizationException::query()->count())->toBe(0);
});

test('an inconsistent repair order stays open while a separate approved repair order can move', function () {
    $advisor = integrityAdvisor();
    [$inconsistent, $inconsistentConcern, $installedPart] = integrityRepairOrder();
    $inconsistent->update([
        'status' => RepairOrderStatus::QualityCheck,
        'mileage_out' => 120050,
    ]);
    $installedPart->update(['procurement_state' => PartProcurementState::Installed]);
    $followUp = addPart($inconsistent, $inconsistentConcern, 'Follow-up clip');

    [$clean, $cleanConcern, $cleanPart] = integrityRepairOrder();
    $cleanConcern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    $clean->update(['status' => RepairOrderStatus::InProgress]);
    $cleanPart->update(['procurement_state' => PartProcurementState::Received]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $inconsistent))
        ->assertOk()
        ->assertSee($inconsistentConcern->summary, false);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$inconsistent, $followUp]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])->assertRedirect();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $inconsistent))
        ->patch(route('operations.repair-orders.lifecycle.update', $inconsistent), [
            'status' => RepairOrderStatus::ReadyPickup->value,
        ])
        ->assertSessionHasErrors('lifecycle');

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lines.procurement.update', [$clean, $cleanPart]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lifecycle.update', $clean), [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])->assertRedirect();

    expect($inconsistent->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and($inconsistentConcern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($installedPart->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($followUp->fresh()->procurement_state)->toBe(PartProcurementState::Ordered)
        ->and($clean->fresh()->status->value)->toBe(RepairOrderStatus::QualityCheck->value)
        ->and($cleanPart->fresh()->procurement_state)->toBe(PartProcurementState::Installed);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$inconsistent, $inconsistentConcern]), [
            'reason' => 'diagnostic',
            'note' => DIAGNOSTIC_EXCEPTION_NOTE,
            'line_ids' => [$installedPart->id],
        ])->assertRedirect();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lifecycle.update', $inconsistent), [
            'status' => RepairOrderStatus::ReadyPickup->value,
        ])->assertRedirect();

    expect($inconsistent->fresh()->status->value)->toBe(RepairOrderStatus::ReadyPickup->value)
        ->and($inconsistentConcern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($inconsistent->fresh())->totalCents())->toBe(0);
});

test('an exception allows added work without increasing approved sales', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);
    approveConcern($repairOrder, $concern, $advisor);
    $part->update(['procurement_state' => PartProcurementState::Received]);

    $approvedBefore = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    $added = addPart($repairOrder, $concern, 'Hardware added after approval');
    $added->update(['procurement_state' => PartProcurementState::Received]);

    $this->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
        'reason' => 'diagnostic',
        'note' => 'Hardware needed to finish the diagnosis. The customer has not approved this line.',
        'line_ids' => [$added->id],
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
        'procurement_state' => PartProcurementState::Installed->value,
    ])->assertRedirect();

    $approvedAfter = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents();
    $invoiceAfter = app(EstimateTotalsCalculator::class)->totalsForApprovedWork($repairOrder->fresh())->totalCents();

    expect($added->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($approvedAfter)->toBe($approvedBefore)
        ->and($invoiceAfter)->toBe($approvedAfter)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh())->totalCents())->toBeGreaterThan($approvedAfter);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Hardware added after approval', false)
        ->assertSee('Needs authorization', false);
});

test('authorization history survives concern deletion', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);
    approveConcern($repairOrder, $concern, $advisor);
    $part->delete();

    $scopeId = ApprovedWorkScope::query()->value('id');

    $this->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertStatus(422);

    $this->deleteJson(route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]))
        ->assertStatus(422)
        ->assertJsonPath('message', WorkCompletionAuthorization::CONCERN_DELETE_BLOCKED);

    expect(fn () => RepairOrderConcern::query()->whereKey($concern->id)->delete())->toThrow(QueryException::class);

    $exceptionConcern = addConcern($repairOrder, 'Diagnostic only');
    $exceptionPart = addPart($repairOrder, $exceptionConcern, 'Scan tool');
    app(RecordAuthorizationExceptionAction::class)->execute(
        $repairOrder,
        $exceptionConcern,
        [$exceptionPart->id],
        AuthorizationExceptionReason::Diagnostic,
        DIAGNOSTIC_EXCEPTION_NOTE,
        $advisor,
    );
    $exceptionPart->delete();
    $exceptionId = AuthorizationException::query()->value('id');

    $this->deleteJson(route('operations.repair-orders.concerns.destroy', [$repairOrder, $exceptionConcern]))
        ->assertStatus(422)
        ->assertJsonPath('message', WorkCompletionAuthorization::CONCERN_DELETE_BLOCKED);

    expect(fn () => RepairOrderConcern::query()->whereKey($exceptionConcern->id)->delete())->toThrow(QueryException::class);

    $token = $advisor->createToken('phone')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/concerns/'.$concern->id)
        ->assertStatus(422)
        ->assertJsonPath('message', WorkCompletionAuthorization::CONCERN_DELETE_BLOCKED);

    $empty = addConcern($repairOrder, 'Empty follow-up');

    $this->actingAs($advisor)
        ->delete(route('operations.repair-orders.concerns.destroy', [$repairOrder, $empty]))
        ->assertRedirect();

    expect(RepairOrderConcern::query()->whereKey($concern->id)->exists())->toBeTrue()
        ->and(RepairOrderConcern::query()->whereKey($exceptionConcern->id)->exists())->toBeTrue()
        ->and(RepairOrderConcern::query()->whereKey($empty->id)->exists())->toBeFalse()
        ->and(ApprovedWorkScope::query()->whereKey($scopeId)->exists())->toBeTrue()
        ->and(AuthorizationException::query()->whereKey($exceptionId)->exists())->toBeTrue();
});

test('authorization exceptions are append-only and the repair order says they are not customer approval', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $labor = addLabor($repairOrder, $concern);
    $this->actingAs($advisor);

    $note = 'Shop truck brake inspection. Not billed to a customer and not an approval.';

    $this->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
        'reason' => 'internal',
        'note' => $note,
        'line_ids' => [$part->id],
        'authorization_exception_concern_id' => $concern->id,
    ])->assertRedirect();

    $exception = AuthorizationException::query()->first();
    $recordedAt = $exception->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A');

    expect(fn () => $exception->update(['note' => 'rewritten']))->toThrow(LogicException::class);
    expect(fn () => $exception->delete())->toThrow(LogicException::class);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('This documents an internal basis for proceeding. It does not record customer approval.', false)
        ->assertSee('data-authorization-exception-line="'.$part->id.'"', false)
        ->assertDontSee('data-authorization-exception-line="'.$labor->id.'"', false)
        ->assertSee('ops-exception-record__basis">Internal', false)
        ->assertSee('Recorded by '.$advisor->name, false)
        ->assertSee($recordedAt, false)
        ->assertSee($note, false)
        ->assertSee('Front brake pads', false)
        ->assertSee('Covered work', false)
        ->assertDontSee('Delete exception', false)
        ->assertDontSee('Edit exception', false);
});

test('exception recording is offered in the work status menu instead of the worksheet', function () {
    $advisor = integrityAdvisor();
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $labor = addLabor($repairOrder, $concern);
    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Advisor-only context that is not exception work.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => true,
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('aria-label="More actions"', false)
        ->assertDontSee('data-scope-more', false)
        ->assertSee('data-authorization-exception-action="record"', false)
        ->assertSee('Record work exception', false)
        ->assertSee('Select a basis', false)
        ->assertSee('This documents an internal basis for proceeding. It does not record customer approval.', false)
        ->assertSee('name="line_ids[]" value="'.$part->id.'"', false)
        ->assertSee('name="line_ids[]" value="'.$labor->id.'"', false)
        ->assertSee('Diagnose front-end wobble', false)
        ->assertDontSee('name="line_ids[]" value="'.$note->id.'"', false)
        ->assertDontSee('ops-scope-settings__exception', false)
        ->assertDontSee('<summary>Record exception</summary>', false)
        ->getContent();

    expect(integrityExceptionActionsInWorkStatusMenu($html, $concern))->toBe(1);

    $this->actingAs($admin)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-authorization-exception-action="record"', false);

    $this->actingAs($technician)
        ->followingRedirects()
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertDontSee('data-authorization-exception-action="record"', false)
        ->assertDontSee('Record work exception', false);

    expect(RecordAuthorizationExceptionAction::actorMayRecord($advisor))->toBeTrue()
        ->and(RecordAuthorizationExceptionAction::actorMayRecord($admin))->toBeTrue()
        ->and(RecordAuthorizationExceptionAction::actorMayRecord($technician))->toBeFalse();
});

test('lines blocked without an exception match what install and completion refuse', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $labor = addLabor($repairOrder, $concern);
    $authorization = app(WorkCompletionAuthorization::class);
    $blockedIds = fn (): array => $authorization->linesBlockedWithoutException($concern->fresh())->pluck('id')->all();

    expect($blockedIds())->toEqualCanonicalizing([$part->id, $labor->id])
        ->and($authorization->installBlockedReason($part->fresh()))->toBe(WorkCompletionAuthorization::INSTALL_BLOCKED)
        ->and($authorization->completionBlockedReason($concern->fresh()))->toBe(WorkCompletionAuthorization::COMPLETION_BLOCKED);

    app(RecordAuthorizationExceptionAction::class)->execute($repairOrder, $concern, [$part->id], AuthorizationExceptionReason::Diagnostic, DIAGNOSTIC_EXCEPTION_NOTE, $advisor);

    expect($blockedIds())->toBe([$labor->id])
        ->and($authorization->installBlockedReason($part->fresh()))->toBeNull()
        ->and($authorization->completionBlockedReason($concern->fresh()))->toBe(WorkCompletionAuthorization::COMPLETION_BLOCKED);

    app(RecordAuthorizationExceptionAction::class)->execute($repairOrder, $concern, [$labor->id], AuthorizationExceptionReason::Diagnostic, DIAGNOSTIC_EXCEPTION_NOTE, $advisor);

    expect($blockedIds())->toBe([])
        ->and($authorization->completionBlockedReason($concern->fresh()))->toBeNull();

    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    addPart($repairOrder, $concern, 'Added after approval');

    expect($blockedIds())->toBe([])
        ->and($authorization->completionBlockedReason($concern->fresh()))->toBeNull();
});

test('the work status menu stops offering an exception once the blocked work is covered and keeps the record viewable', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    app(RecordAuthorizationExceptionAction::class)->execute($repairOrder, $concern, [$part->id], AuthorizationExceptionReason::Warranty, DIAGNOSTIC_EXCEPTION_NOTE, $advisor);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-authorization-exception-line="'.$part->id.'"', false)
        ->assertSee('ops-exception-record__basis">Warranty', false)
        ->assertSee(DIAGNOSTIC_EXCEPTION_NOTE, false)
        ->getContent();

    expect(integrityExceptionActionsInWorkStatusMenu($html, $concern))->toBe(0)
        ->and(AuthorizationException::query()->count())->toBe(1);
});

test('an approved concern does not offer an exception', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern] = integrityRepairOrder();
    addLabor($repairOrder, $concern);
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('data-authorization-exception-action="record"', false)
        ->assertDontSee('aria-label="More actions"', false)
        ->getContent();

    expect(integrityExceptionActionsInWorkStatusMenu($html, $concern))->toBe(0);
});

test('an incomplete exception explanation reopens the dialog with the error', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    $this->followingRedirects()
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
            'reason' => 'warranty',
            'note' => 'too short',
            'line_ids' => [$part->id],
            'authorization_exception_concern_id' => $concern->id,
        ])
        ->assertOk()
        ->assertSee('Describe which work this exception covers and why this basis applies.', false)
        ->assertSee('data-authorization-exception-reopen="'.$concern->id.'"', false)
        ->assertSee('too short', false);

    expect(AuthorizationException::query()->count())->toBe(0);
});

function integrityExceptionActionsInWorkStatusMenu(string $html, RepairOrderConcern $concern): int
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return (new DOMXPath($document))
        ->query('//*[@id="concern-'.$concern->id.'"]//*[@data-concern-work-status-choices]//*[@data-authorization-exception-action="record"]')
        ->length;
}

function integrityAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

function allowEstimateToQualityCheck(): void
{
    $transition = RepairOrderStatusTransition::query()->create([
        'from_status_slug' => RepairOrderStatus::Estimate->value,
        'to_status_slug' => RepairOrderStatus::QualityCheck->value,
        'active' => true,
    ]);

    foreach (['admin', 'advisor', 'technician'] as $role) {
        RepairOrderStatusTransitionRole::query()->create([
            'transition_id' => $transition->id,
            'role' => $role,
        ]);
    }

    app(RepairOrderStatusCatalog::class)->forgetCache();
}

function approveConcern(RepairOrder $repairOrder, RepairOrderConcern $concern, User $advisor): void
{
    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder,
        $concern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );
}

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderLine}
 */
function integrityRepairOrder(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Paul',
        'last_name' => 'Howell',
        'phone' => '555-'.random_int(1000, 9999),
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'INT'.random_int(100, 999),
        'year' => 2014,
        'make' => 'Ford',
        'model' => 'F-150',
        'vin' => '1FTMF1CT5EKE'.random_int(10000, 99999),
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 120000,
        'mileage_out' => 120050,
        'concern_summary' => 'Front brakes grinding.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $part = addPart($repairOrder, $concern, 'Front brake pads');

    return [$repairOrder, $concern, $part];
}

function addPart(RepairOrder $repairOrder, RepairOrderConcern $concern, string $description): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => $description,
        'quantity' => '1.00',
        'unit_price_cents' => 8068,
        'part_cost_cents' => 4000,
        'subtotal_cents' => 8068,
        'total_cents' => 8068,
    ]);
}

function addConcern(RepairOrder $repairOrder, string $summary): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
}

function addLabor(RepairOrder $repairOrder, RepairOrderConcern $concern): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnose front-end wobble',
        'quantity' => '1.00',
        'unit_price_cents' => 22201,
        'subtotal_cents' => 22201,
        'total_cents' => 22201,
    ]);
}

/** An approval recorded before approved amounts were kept: a scope with line ids only. */
function legacyApproval(RepairOrder $repairOrder, RepairOrderConcern $concern, RepairOrderLine $line, User $advisor): void
{
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    ApprovedWorkScope::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'line_ids' => [$line->id],
        'recorded_by_user_id' => $advisor->id,
    ]);
}
