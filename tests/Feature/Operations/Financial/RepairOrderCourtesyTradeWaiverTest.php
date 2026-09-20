<?php

use App\Ark\Operations\Documents\InvoicePdfFinancialSnapshot;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Financial\WaiveRepairOrderBalanceAction;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('waiving remaining balance as trade preserves invoice total and clears balance', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoiceTotalBefore = InvoiceSnapshotBuilder::invoiceTotalCents(
        app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh())->snapshot_json ?? []
    );

    $this->post(route('operations.repair-orders.waive-balance.store', $repairOrder), [
        'disposition' => RepairOrderCollectionDisposition::Trade->value,
        'reason' => 'Trade labor for parts vendor',
    ])->assertRedirect()
        ->assertSessionHas('status');

    $repairOrder = $repairOrder->fresh();
    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder);
    $writeOff = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::WriteOff)
        ->first();

    expect($invoiceTotalBefore)->toBe(15000)
        ->and($balance->invoiceTotalCents)->toBe(15000)
        ->and($balance->writeOffsCents)->toBe(15000)
        ->and($balance->paymentsAppliedCents)->toBe(0)
        ->and($balance->balanceDueCents)->toBe(0)
        ->and($balance->isPaid())->toBeTrue()
        ->and($repairOrder->collection_disposition)->toBe(RepairOrderCollectionDisposition::Trade)
        ->and($repairOrder->collection_disposition_reason)->toBe('Trade labor for parts vendor')
        ->and($writeOff)->not->toBeNull()
        ->and($writeOff->amount_cents)->toBe(15000);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
        'review_request_sent' => '1',
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue();

    $presenter = app(RepairOrderFinancialPresenter::class)->for(
        $repairOrder->fresh(),
        app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh()),
    );

    expect($presenter['wouldHaveCost'])->toBe('$150.00')
        ->and($presenter['collected'])->toBe('$0.00')
        ->and($presenter['waived'])->toBe('$150.00')
        ->and($presenter['collectionDispositionLabel'])->toBe('Trade')
        ->and($presenter['excludesFromPostedSales'])->toBeTrue()
        ->and($presenter['showFinancialRail'])->toBeTrue()
        ->and($presenter['financialRailReadOnly'])->toBeTrue();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Would have cost')
        ->assertSee('$150.00')
        ->assertSee('Waived')
        ->assertSee('Trade');
});

test('courtesy and trade waived repair orders are excluded from posted sales cents', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $trade = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($trade);
    app(WaiveRepairOrderBalanceAction::class)->execute(
        $trade->fresh(),
        RepairOrderCollectionDisposition::Trade,
        'Trade for parts',
    );

    $retail = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($retail);
    payRepairOrderInFull($retail);

    $posted = OperationalReportTotals::postedSalesCentsByRepairOrderId([
        $trade->id,
        $retail->id,
    ]);

    expect($posted[$trade->id])->toBe(0)
        ->and($posted[$retail->id])->toBe(15000)
        ->and(OperationalReportTotals::postedSalesCents([$trade->id, $retail->id]))->toBe(15000);
});

test('bad debt waiver remains in posted sales cents', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    app(WaiveRepairOrderBalanceAction::class)->execute(
        $repairOrder->fresh(),
        RepairOrderCollectionDisposition::BadDebt,
        'Customer unreachable after pickup',
    );

    expect(OperationalReportTotals::postedSalesCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])
        ->toBe(15000);
});

test('invoice pdf snapshot includes courtesy waiver customer label', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    app(WaiveRepairOrderBalanceAction::class)->execute(
        $repairOrder->fresh(),
        RepairOrderCollectionDisposition::Courtesy,
        'Shop owner vehicle',
    );

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $snapshot = app(InvoicePdfFinancialSnapshot::class)->append($invoice, $invoice->snapshot_json ?? []);

    expect($snapshot['financial']['collection_waiver_label'])->toBe('Courtesy — balance waived')
        ->and($snapshot['financial']['write_offs_cents'])->toBe(15000);

    $snapshot['document_footer'] = app(\App\Ark\Operations\Documents\DocumentFooterPresenter::class)->present($snapshot);

    $html = view('operations.documents.partials._document-footer', [
        'variant' => 'pdf',
        'snapshot' => $snapshot,
    ])->render();

    expect($html)->toContain('Courtesy — balance waived')
        ->toContain('Write-offs');
});

test('waive balance requires disposition and reason', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->post(route('operations.repair-orders.waive-balance.store', $repairOrder), [])
        ->assertSessionHasErrors(['disposition', 'reason']);

    $this->post(route('operations.repair-orders.waive-balance.store', $repairOrder), [
        'disposition' => RepairOrderCollectionDisposition::Goodwill->value,
        'reason' => '',
    ])->assertSessionHasErrors(['reason']);
});
