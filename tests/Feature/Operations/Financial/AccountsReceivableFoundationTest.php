<?php

use App\Ark\Operations\Financial\AccountsReceivableOutstandingQuery;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\PostRepairOrderToReceivablesAction;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderCloseoutAuthority;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

test('paid close remains blocked while an invoice balance is outstanding', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    expect(app(RepairOrderCloseoutAuthority::class)->blockingReason($repairOrder->fresh(), 'paid'))
        ->toContain('Collect the full balance');
});

test('on-account close posts the unpaid invoice to receivables and keeps it collectible', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $closed = app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        $this->advisor,
        'on_account',
    );

    $invoice = $closed->fresh()->balanceDue()->hasIssuedInvoice
        ? app(\App\Ark\Operations\Financial\BalanceDueCalculator::class)->issuedInvoice($closed)
        : null;

    expect($closed->status->value)->toBe(RepairOrderStatus::Closed->value)
        ->and($closed->close_variant_key)->toBe('on_account')
        ->and($closed->balanceDue()->balanceDueCents)->toBe(15000)
        ->and($invoice)->not->toBeNull()
        ->and($invoice->posted_to_receivables_at)->not->toBeNull()
        ->and($invoice->due_at)->not->toBeNull()
        ->and($invoice->payment_terms_days)->toBe(PostRepairOrderToReceivablesAction::DEFAULT_TERMS_DAYS)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued->value);

    $dashboard = app(AccountsReceivableOutstandingQuery::class)->dashboard();

    expect($dashboard['total_outstanding_cents'])->toBe(15000)
        ->and($dashboard['accounts'])->toHaveCount(1)
        ->and($dashboard['invoices'][0]['balance_due_cents'])->toBe(15000);

    $this->get(route('operations.financials.accounts-receivable.index'))
        ->assertOk()
        ->assertSee('Accounts Receivable')
        ->assertSee('INV #');

    $this->get(route('operations.financials.accounts-receivable.customer', $closed->customer_id))
        ->assertOk()
        ->assertSee($closed->customer->name);
});

test('a payment can be recorded after an on-account close without reopening the RO', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $closed = app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        $this->advisor,
        'on_account',
    );

    expect($closed->allowsAccountsReceivableCollection())->toBeTrue();

    app(RecordLedgerEntryAction::class)->recordPayment(
        $closed->fresh(),
        15000,
        PaymentMethod::Check,
        $this->advisor,
        'Fleet check 1001',
    );

    $fresh = $closed->fresh();
    expect($fresh->balanceDue()->balanceDueCents)->toBe(0)
        ->and($fresh->balanceDue()->isPaid())->toBeTrue()
        ->and($fresh->status->value)->toBe(RepairOrderStatus::Closed->value);

    $dashboard = app(AccountsReceivableOutstandingQuery::class)->dashboard();
    expect($dashboard['total_outstanding_cents'])->toBe(0)
        ->and($dashboard['invoices'])->toBe([]);
});

test('on-account close is refused when the invoice is already paid', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    expect(app(RepairOrderCloseoutAuthority::class)->blockingReason($repairOrder->fresh(), 'on_account'))
        ->toContain('already paid');
});
