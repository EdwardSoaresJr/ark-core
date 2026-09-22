<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Approvals\RecordCustomerAuthorizationAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Labor\TechnicianFlagRecognition;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\AuthorizationException;
use App\Ark\Operations\RepairOrders\AuthorizationExceptionReason;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RecordAuthorizationExceptionAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
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

test('an approved concern installs only the lines in that approval and keeps approved dollars on the calculator', function () {
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

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertSessionHas('error');

    expect($added->fresh()->procurement_state)->not->toBe(PartProcurementState::Installed);

    approveConcern($repairOrder, $concern->fresh(), $advisor);

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBe($approvedBefore + 8068);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Caliper', false)
        ->assertDontSee('Needs authorization');

    $added->update(['procurement_state' => PartProcurementState::Received]);

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
        'procurement_state' => PartProcurementState::Installed->value,
    ])->assertRedirect();

    expect($added->fresh()->procurement_state)->toBe(PartProcurementState::Installed);
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

    $added = addPart($repairOrder, $concern, 'Caliper added after approval');
    $added->update(['procurement_state' => PartProcurementState::Received]);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $added]), [
            'procurement_state' => PartProcurementState::Installed->value,
        ])
        ->assertSessionHas('error');

    expect($added->fresh()->procurement_state)->toBe(PartProcurementState::Received)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh())->totalCents())->toBeGreaterThan(0);
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

test('partial approval installs the approved concern and leaves the other concern recommended', function () {
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
        ->assertSessionHas('error');

    $repairOrder->update(['status' => RepairOrderStatus::InProgress]);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::QualityCheck->value,
        ])->assertRedirect();

    expect($approvedPart->fresh()->procurement_state)->toBe(PartProcurementState::Installed)
        ->and($openPart->fresh()->procurement_state)->toBe(PartProcurementState::Received)
        ->and($added->fresh()->procurement_state)->toBe(PartProcurementState::Received)
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

test('authorization exceptions are append-only and the worksheet says they are not customer consent', function () {
    $advisor = integrityAdvisor();
    [$repairOrder, $concern, $part] = integrityRepairOrder();
    $this->actingAs($advisor);

    $this->post(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]), [
        'reason' => 'internal',
        'note' => 'Shop truck brake inspection. Not billed to a customer and not an approval.',
        'line_ids' => [$part->id],
    ])->assertRedirect();

    $exception = AuthorizationException::query()->first();

    expect(fn () => $exception->update(['note' => 'rewritten']))->toThrow(LogicException::class);
    expect(fn () => $exception->delete())->toThrow(LogicException::class);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('It does not approve the work or record customer consent.', false);
});

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
