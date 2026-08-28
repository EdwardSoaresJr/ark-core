<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Approvals\ResolveStaffAuthorizationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentFooterPresenter;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('advisor can record customer authorization from review without per-concern dropdowns', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $recommendedConcern, $approvedConcern] = repairOrderForCustomerAuthorization();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('name="concern_dispositions', false)
        ->assertDontSee('Authorization type', false);

    $recommendedConcern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    $this->post(route('operations.repair-orders.authorization.store', $repairOrder), [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Morgan Brown',
        'approved_amount' => '255.45',
        'notes' => 'Customer signed estimate at counter.',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');

    $repairOrder->refresh();
    $recommendedConcern->refresh();

    expect($recommendedConcern->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(ApprovalEvent::query()->where('visit_id', $repairOrder->id)->count())->toBe(1);

    $approval = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->first();

    expect($approval)
        ->approval_type->toBe(ApprovalType::Repair)
        ->source->toBe(ApprovalSource::InPerson)
        ->approved_by->toBe('Morgan Brown')
        ->notes->toBe('Customer signed estimate at counter.')
        ->and($approval->approved_amount_cents)->toBeGreaterThan(0);
});

test('advisor authorization derives partial type from mixed scope dispositions', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $recommendedConcern, $approvedConcern] = repairOrderForCustomerAuthorization();

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Deferred coolant hose',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 3,
    ]);

    $this->post(route('operations.repair-orders.authorization.store', $repairOrder), [
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Morgan Brown',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');

    $approval = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->latest('id')->first();

    expect($approval->approval_type)->toBe(ApprovalType::Partial)
        ->and($approval->source)->toBe(ApprovalSource::Phone);
});

test('advisor cannot record authorization before any scope is approved', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $recommendedConcern] = repairOrderForCustomerAuthorization();

    $recommendedConcern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);
    $repairOrder->concerns()->where('disposition', RepairOrderConcernDisposition::Approved)->update([
        'disposition' => RepairOrderConcernDisposition::Recommended,
    ]);

    $this->post(route('operations.repair-orders.authorization.store', $repairOrder->fresh()), [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Morgan Brown',
    ])->assertSessionHasErrors('authorization');

    expect(ApprovalEvent::query()->where('visit_id', $repairOrder->id)->count())->toBe(0);
});

test('advisor can revoke customer authorization and revert approved scopes', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $recommendedConcern, $approvedConcern] = repairOrderForCustomerAuthorization();

    $recommendedConcern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    $repairOrder->update(['status' => RepairOrderStatus::Approved]);

    $this->post(route('operations.repair-orders.authorization.store', $repairOrder), [
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Jean-Luc Martin',
        'approved_amount' => '3784.80',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');

    $approval = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->sole();

    $this->post(route('operations.repair-orders.authorization.revoke', [$repairOrder, $approval]), [
        'source' => ApprovalSource::Phone->value,
        'revoked_by' => 'Jean-Luc Martin',
        'notes' => 'Customer called to cancel approved work.',
        'concern_ids' => [$recommendedConcern->id, $approvedConcern->id],
        'revert_disposition' => RepairOrderConcernDisposition::Recommended->value,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#authorization-rail');

    $repairOrder->refresh();
    $recommendedConcern->refresh();
    $approvedConcern->refresh();
    $approval->load('revocation');

    expect($approval->isRevoked())->toBeTrue()
        ->and($approval->revocation->revoked_by)->toBe('Jean-Luc Martin')
        ->and($recommendedConcern->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($approvedConcern->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($repairOrder->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Revoked', false)
        ->assertSee('Customer called to cancel approved work.', false);
});

test('authorization cannot be revoked after final invoice is issued', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);

    $approval = ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 15000,
        'source' => ApprovalSource::Phone,
        'approved_by' => 'Customer',
        'approved_at' => now(),
    ]);

    $concern = $repairOrder->concerns()->first();

    $this->post(route('operations.repair-orders.authorization.revoke', [$repairOrder, $approval]), [
        'source' => ApprovalSource::Phone->value,
        'revoked_by' => 'Customer',
        'concern_ids' => [$concern->id],
        'revert_disposition' => RepairOrderConcernDisposition::Recommended->value,
    ])->assertSessionHasErrors('authorization');

    expect($approval->fresh()->isRevoked())->toBeFalse();
});

test('staff authorization type resolves from scope dispositions', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    [$repairOrder, $recommendedConcern, $approvedConcern] = repairOrderForCustomerAuthorization();

    $resolver = app(ResolveStaffAuthorizationType::class);

    expect($resolver->fromRepairOrder($repairOrder->fresh()))->toBe(ApprovalType::Partial);

    $recommendedConcern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    expect($resolver->fromRepairOrder($repairOrder->fresh()))->toBe(ApprovalType::Repair);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Deferred coolant hose',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 3,
    ]);

    expect($resolver->fromRepairOrder($repairOrder->fresh()))->toBe(ApprovalType::Partial);

    $approvedConcern->update(['recommendation_intent' => 'diagnostic']);
    $repairOrder->concerns()->whereKey($recommendedConcern->id)->delete();

    expect($resolver->fromRepairOrder($repairOrder->fresh()))->toBe(ApprovalType::Diagnostic);
});

test('estimate pdf includes per-concern customer decision marks only', function () {
    $snapshot = app(EstimateSnapshotBuilder::class)->build(repairOrderForCustomerAuthorization()[0]);

    $presented = app(DocumentPdfPresenter::class)->prepareForCustomer($snapshot);
    $html = view('operations.documents.pdf.document', [
        'snapshot' => $presented,
    ])->render();

    expect($html)
        ->toContain('Customer decision')
        ->toContain('Approve')
        ->toContain('Defer')
        ->toContain('footer-decision-area')
        ->toContain('footer-decision-heading')
        ->not->toContain('I approve all recommended repairs listed above')
        ->not->toContain('Per-concern selections control authorized scope');

    $pendingScope = (string) Str::of($html)->after('A/C not cold')->before('</article>');

    expect($pendingScope)->toContain('Customer decision');

    $footer = app(DocumentFooterPresenter::class)->present($presented);

    expect($footer)->not->toHaveKey('show_approve_all_recommended');
});

test('estimate pdf scope header keeps amount top right and shows approval inline with intent', function () {
    [$repairOrder, $recommendedConcern, $approvedConcern] = repairOrderForCustomerAuthorization();

    $recommendedConcern->update(['recommendation_intent' => 'diagnostic']);
    $approvedConcern->update(['recommendation_intent' => 'immediate_attention']);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Deferred coolant hose',
        'recommendation_intent' => 'plan_soon',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 3,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft overheating diagnostic',
        'recommendation_intent' => 'diagnostic',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 4,
    ]);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines']));
    $presented = app(DocumentPdfPresenter::class)->prepareForCustomer($snapshot);

    $html = view('operations.documents.pdf.document', [
        'snapshot' => $presented,
    ])->render();

    expect($html)
        ->toContain('concern-header-total')
        ->toContain('concern-priority-badge--immediate_attention">Immediate Attention</p>')
        ->toContain('concern-priority-badge--diagnostic">Diagnostic</p>')
        ->toContain('concern-priority-badge--plan_soon">Plan Soon</p>')
        ->toContain('concern-header-decision--approved')
        ->toContain('concern-header-decision-mark">✓</span>')
        ->toContain('concern-header-decision--deferred')
        ->toContain('concern-header-decision--recommended')
        ->toContain('concern-header-status')
        ->not->toContain('concern-intent-group')
        ->not->toContain('Draft overheating diagnostic')
        ->not->toContain('concern-header-decision">Draft</span>')
        ->not->toContain('Estimated Work');

    $approvedScope = (string) Str::of($html)->after('Prior approved brake work')->before('</article>')->value();

    expect($approvedScope)
        ->toContain('concern-header-total')
        ->toContain('concern-header-status')
        ->toContain('concern-header-decision--approved')
        ->toContain('concern-header-decision-mark">✓</span>')
        ->not->toContain('Customer decision')
        ->not->toContain('concern-header-intent">Immediate Attention</span>');

    $pendingScope = (string) Str::of($html)->after('A/C not cold')->before('</article>');

    expect($pendingScope)
        ->toContain('concern-header-decision--recommended')
        ->toContain('Pending')
        ->not->toContain('concern-header-decision--recommended">Recommended')
        ->toContain('Customer decision');

    expect($html)->toContain('If All Recommendations Are Approved');
    expect($html)->toContain('Approved Work Breakdown');
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderConcern}
 */
function repairOrderForCustomerAuthorization(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '555-0144',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2013,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'A/C not cold',
    ]);

    $recommendedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'A/C not cold',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $approvedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Prior approved brake work',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 2,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $recommendedConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'A/C performance diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15593,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $approvedConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 9900,
    ]);

    return [$repairOrder->fresh(['customer', 'concerns.lines']), $recommendedConcern, $approvedConcern];
}
