<?php

use App\Ark\Operations\Financial\AllocateCustomerPaymentAction;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\VoidCustomerPaymentReceiptAction;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->advisor);
});

test('one check payment allocates across multiple invoices and leaves the remainder unpaid', function () {
    [$roA, $invoiceA] = arOnAccountInvoiceForCustomer();
    $customer = $roA->customer;
    [$roB, $invoiceB] = arOnAccountInvoiceForCustomer($customer);
    [$roC, $invoiceC] = arOnAccountInvoiceForCustomer($customer);

    expect(app(BalanceDueCalculator::class)->forRepairOrder($roA)->balanceDueCents)->toBe(15000)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB)->balanceDueCents)->toBe(15000)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roC)->balanceDueCents)->toBe(15000);

    $intent = (string) Str::uuid();
    $receipt = app(AllocateCustomerPaymentAction::class)->execute(
        $customer->fresh(),
        30000,
        PaymentMethod::Check,
        [
            ['invoice_id' => $invoiceA->id, 'amount_cents' => 15000],
            ['invoice_id' => $invoiceB->id, 'amount_cents' => 15000],
        ],
        $intent,
        $this->advisor,
        'CHK-300',
    );

    expect($receipt->amount_cents)->toBe(30000)
        ->and($receipt->allocated_cents)->toBe(30000)
        ->and($receipt->unapplied_cents)->toBe(0)
        ->and($receipt->ledgerEntries()->where('entry_type', LedgerEntryType::Payment)->count())->toBe(2)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roC->fresh())->balanceDueCents)->toBe(15000)
        ->and(app(BalanceDueCalculator::class)->issuedInvoice($roA->fresh())->status)->toBe(InvoiceStatus::Paid->value)
        ->and(app(BalanceDueCalculator::class)->issuedInvoice($roC->fresh())->status)->toBe(InvoiceStatus::Issued->value);

    $replay = app(AllocateCustomerPaymentAction::class)->execute(
        $customer->fresh(),
        30000,
        PaymentMethod::Check,
        [
            ['invoice_id' => $invoiceA->id, 'amount_cents' => 15000],
            ['invoice_id' => $invoiceB->id, 'amount_cents' => 15000],
        ],
        $intent,
        $this->advisor,
        'CHK-300',
    );

    expect($replay->id)->toBe($receipt->id)
        ->and(CustomerPaymentReceipt::query()->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roC->fresh())->balanceDueCents)->toBe(15000);
});

test('partial allocation and check remainder become store credit', function () {
    [$repairOrder, $invoice] = arOnAccountInvoiceForCustomer();

    $receipt = app(AllocateCustomerPaymentAction::class)->execute(
        $repairOrder->customer,
        20000,
        PaymentMethod::Check,
        [['invoice_id' => $invoice->id, 'amount_cents' => 10000]],
        (string) Str::uuid(),
        $this->advisor,
        'CHK-PARTIAL',
    );

    expect($receipt->allocated_cents)->toBe(10000)
        ->and($receipt->unapplied_cents)->toBe(10000)
        ->and($receipt->change_cents)->toBe(0)
        ->and($receipt->amount_cents)->toBe($receipt->allocated_cents + $receipt->unapplied_cents + $receipt->change_cents)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(5000)
        ->and((int) $repairOrder->customer->fresh()->store_credit_balance_cents)->toBe(10000);
});

test('cash overpayment records explicit change and never creates store credit', function () {
    [$repairOrder, $invoice] = arOnAccountInvoiceForCustomer();

    $receipt = app(AllocateCustomerPaymentAction::class)->execute(
        $repairOrder->customer,
        20000,
        PaymentMethod::Cash,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        (string) Str::uuid(),
        $this->advisor,
        'CASH-CHANGE',
    );

    expect($receipt->amount_cents)->toBe(20000)
        ->and($receipt->allocated_cents)->toBe(15000)
        ->and($receipt->unapplied_cents)->toBe(0)
        ->and($receipt->change_cents)->toBe(5000)
        ->and($receipt->amount_cents)->toBe($receipt->allocated_cents + $receipt->unapplied_cents + $receipt->change_cents)
        ->and((int) $repairOrder->customer->fresh()->store_credit_balance_cents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('reusing an intent key with different payment details is rejected', function () {
    [$repairOrder, $invoice] = arOnAccountInvoiceForCustomer();
    $intent = (string) Str::uuid();

    app(AllocateCustomerPaymentAction::class)->execute(
        $repairOrder->customer,
        15000,
        PaymentMethod::Check,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        $intent,
        $this->advisor,
        'CHK-ORIG',
    );

    expect(fn () => app(AllocateCustomerPaymentAction::class)->execute(
        $repairOrder->customer,
        10000,
        PaymentMethod::Check,
        [['invoice_id' => $invoice->id, 'amount_cents' => 10000]],
        $intent,
        $this->advisor,
        'CHK-DIFF',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(CustomerPaymentReceipt::query()->count())->toBe(1)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);
});

test('voiding a receipt reverses allocations and store credit', function () {
    [$repairOrder, $invoice] = arOnAccountInvoiceForCustomer();

    $receipt = app(AllocateCustomerPaymentAction::class)->execute(
        $repairOrder->customer,
        15000,
        PaymentMethod::Check,
        [['invoice_id' => $invoice->id, 'amount_cents' => 15000]],
        (string) Str::uuid(),
        $this->advisor,
        'CHK-VOID',
    );

    expect(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(0);

    $this->actingAs($this->admin);
    app(VoidCustomerPaymentReceiptAction::class)->execute($receipt->fresh(), $this->admin);

    expect($receipt->fresh()->isVoided())->toBeTrue()
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->balanceDueCents)->toBe(15000)
        ->and(app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh())->status)->toBe(InvoiceStatus::Issued->value);
});

test('receive payment form posts a multi-invoice allocation through the AR UI', function () {
    [$roA, $invoiceA] = arOnAccountInvoiceForCustomer();
    $customer = $roA->customer;
    [$roB, $invoiceB] = arOnAccountInvoiceForCustomer($customer);

    $this->get(route('operations.financials.accounts-receivable.receive-payment.create', $customer))
        ->assertOk()
        ->assertSee('Receive Payment');

    $this->post(route('operations.financials.accounts-receivable.receive-payment.store', $customer), [
        'amount' => '300.00',
        'payment_method' => PaymentMethod::Check->value,
        'reference' => 'CHK-UI',
        \App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency::FIELD => (string) Str::uuid(),
        'allocations' => [
            ['invoice_id' => $invoiceA->id, 'selected' => '1', 'amount' => '150.00'],
            ['invoice_id' => $invoiceB->id, 'selected' => '1', 'amount' => '150.00'],
        ],
    ])->assertRedirect(route('operations.financials.accounts-receivable.customer', $customer));

    expect(app(BalanceDueCalculator::class)->forRepairOrder($roA->fresh())->balanceDueCents)->toBe(0)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($roB->fresh())->balanceDueCents)->toBe(0);
});
