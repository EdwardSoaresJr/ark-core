<?php

use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrderIndexMoney;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

it('labels building-estimate totals as Estimate and preserves cents', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    // $150.00 labor from fixture; force odd cents via line edit after recalculate is already done.
    $line = $repairOrder->lines()->first();
    $line->forceFill(['unit_price_cents' => 15050])->save();
    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder->fresh(['lines.concern'])]));

    expect($money[$repairOrder->id])->toMatchArray([
        'label' => '$150.50',
        'caption' => 'Estimate',
        'collectible' => false,
    ]);
});

it('does not mark approved uninvoiced work as collectible', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder->fresh(['lines.concern'])]));

    expect($money[$repairOrder->id]['caption'])->toBe('Estimate')
        ->and($money[$repairOrder->id]['collectible'])->toBeFalse()
        ->and($money[$repairOrder->id]['label'])->toBe('$150.00');
});

it('shows estimate with deposit-on-file when an advance exists without an invoice', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);
    app(RecordLedgerEntryAction::class)->recordDeposit($repairOrder, 5000, PaymentMethod::Cash);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder->fresh(['lines.concern'])]));

    expect($money[$repairOrder->id])->toMatchArray([
        'label' => '$150.00',
        'caption' => 'Estimate · deposit on file',
        'collectible' => false,
    ]);
});

it('projects balance due from an issued invoice even when status is still Completed', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Completed);
    issueFinalInvoiceFor($repairOrder);
    // issueFinalInvoiceFor moves status to ReadyPickup — put it back to Completed to prove status is not the authority.
    $repairOrder->fresh()->forceFill(['status' => RepairOrderStatus::Completed])->save();
    $repairOrder = $repairOrder->fresh(['lines.concern']);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder]));

    expect($money[$repairOrder->id])->toMatchArray([
        'label' => '$150.00',
        'caption' => 'Balance due',
        'collectible' => true,
    ]);
});

it('projects balance due for Ready Pickup invoices', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh(['lines.concern']);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder]));

    expect($money[$repairOrder->id]['caption'])->toBe('Balance due')
        ->and($money[$repairOrder->id]['collectible'])->toBeTrue()
        ->and($money[$repairOrder->id]['label'])->toBe('$150.00');
});

it('labels a fully paid invoice as Paid with the invoice total', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder->fresh());
    $repairOrder = $repairOrder->fresh(['lines.concern']);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder]));

    expect($money[$repairOrder->id])->toMatchArray([
        'label' => '$150.00',
        'caption' => 'Paid',
        'collectible' => false,
    ]);
});

it('shows remaining balance due after a partial payment', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);
    app(RecordLedgerEntryAction::class)->recordPayment($repairOrder->fresh(), 5000, PaymentMethod::Cash);
    $repairOrder = $repairOrder->fresh(['lines.concern']);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder]));

    expect($money[$repairOrder->id])->toMatchArray([
        'label' => '$100.00',
        'caption' => 'Balance due',
        'collectible' => true,
    ]);
});

it('labels closed on-account invoices as On account and collectible', function (): void {
    [$closed] = arOnAccountInvoiceForCustomer();
    $closed = $closed->fresh(['lines.concern']);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$closed]));

    expect($money[$closed->id])->toMatchArray([
        'label' => '$150.00',
        'caption' => 'On account',
        'collectible' => true,
    ]);
});

it('labels a paid closed historical RO as Paid', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder->fresh());
    $closed = app(\App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        auth()->user(),
        'paid',
    )->fresh(['lines.concern']);

    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$closed]));

    expect($money[$closed->id])->toMatchArray([
        'label' => '$150.00',
        'caption' => 'Paid',
        'collectible' => false,
    ]);
});

it('renders an em dash when the RO has no financial lines', function (): void {
    $repairOrder = zeroBalanceCourtesyRepairOrder(RepairOrderStatus::Estimate);
    // courtesy line is $0 — treat as no charges for index money.
    $money = app(RepairOrderIndexMoney::class)->forPage(Collection::make([$repairOrder->fresh(['lines.concern'])]));

    expect($money[$repairOrder->id])->toMatchArray([
        'label' => '—',
        'caption' => null,
        'collectible' => false,
    ]);
});

it('batches one balance map for the page instead of filtering by status', function (): void {
    $estimate = financialCloseoutRepairOrder(RepairOrderStatus::Estimate);
    $ready = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($ready);
    $ready = $ready->fresh();

    $page = Collection::make([
        $estimate->fresh(['lines.concern']),
        $ready->fresh(['lines.concern']),
    ]);

    $money = app(RepairOrderIndexMoney::class)->forPage($page);

    expect($money[$estimate->id]['caption'])->toBe('Estimate')
        ->and($money[$estimate->id]['collectible'])->toBeFalse()
        ->and($money[$ready->id]['caption'])->toBe('Balance due')
        ->and($money[$ready->id]['collectible'])->toBeTrue();
});

it('renders collectible captions on the Repair Orders index list', function (): void {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    issueFinalInvoiceFor($repairOrder);

    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.index', ['view' => 'list']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Balance due')
        ->and($html)->toContain('$150.00')
        ->and($html)->toContain('ark-job-board-money--due');
});
