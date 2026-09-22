<?php

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentPaidAt;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

test('ro review shows generate final invoice when ready for pickup without invoice', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Generate Final Invoice')
        ->assertSee('Ready for final invoice')
        ->assertSee('Not issued')
        ->assertSee('Record deposit in ledger')
        ->assertDontSee('Record Payment')
        ->assertDontSee('Mark Payment Received');
});

test('moving to ready pickup auto issues final invoice', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::ReadyPickup->value,
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#financial-rail');

    $invoice = $repairOrder->fresh()
        ->estimateDocuments()
        ->where('document_type', FinancialDocumentType::Invoice->value)
        ->first();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue()
        ->and($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Issued->value);
});

test('builder surface surfaces financial rail for deposits and payments', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Record deposit in ledger')
        ->assertSee('id="financial-rail"', false);
});

test('financial rail surfaces deposit capture before ready pickup', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::InProgress);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Record deposit in ledger')
        ->assertSee('Pre-invoice')
        ->assertDontSee('Generate Final Invoice');
});

test('estimate totals panel shows balance due when deposit is on file', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '50.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Deposit on file')
        ->assertSee('Balance Due')
        ->assertSee('−$50.00')
        ->assertSee('$100.00');
});

test('estimate totals panel shows balance due after partial payment', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '60.00',
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Payments')
        ->assertSee('Settlement balance')
        ->assertSee('−$60.00')
        ->assertSee('$90.00');
});

test('deposit can be recorded before final invoice at any open stage', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '50.00',
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Drop-off deposit',
        'deposit_confirmed' => '1',
    ])->assertRedirect();

    $entry = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->sole();

    expect($entry->amount_cents)->toBe(5000)
        ->and($repairOrder->fresh()->balanceDue()->unappliedDepositsCents)->toBe(5000);
});

test('invoice generation action creates invoice snapshot', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();

    $this->post(route('operations.repair-orders.invoice.store', $repairOrder))
        ->assertRedirect();

    $invoice = $repairOrder->fresh()->estimateDocuments()->where('document_type', FinancialDocumentType::Invoice->value)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Issued->value)
        ->and($invoice->snapshot_json['document_type'])->toBe('invoice');
});

test('duplicate invoice generation is blocked', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.invoice.store', $repairOrder->fresh()))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHasErrors('invoice');

    expect($repairOrder->fresh()->estimateDocuments()->where('document_type', FinancialDocumentType::Invoice->value)->count())->toBe(1);
});

test('payment form is hidden before invoice and visible after invoice', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Record deposit in ledger')
        ->assertDontSee('Record Payment');

    issueFinalInvoiceFor($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Record Payment')
        ->assertSee('name="amount"', false)
        ->assertSee('name="payment_method"', false)
        ->assertSee('name="paid_at"', false)
        ->assertDontSee('Generate Final Invoice');
});

test('record payment accepts optional backdated paid date', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $paidDate = now()->subMonths(2)->toDateString();

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '150.00',
        'payment_method' => 'cash',
        'paid_at' => $paidDate,
        'reference' => 'Historical reconciliation',
    ])->assertRedirect();

    $entry = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->sole();

    $expectedRecordedAt = RepairOrderPaymentPaidAt::fromDateInput($paidDate);

    expect($entry->recorded_at->toDateTimeString())->toBe($expectedRecordedAt->toDateTimeString())
        ->and($repairOrder->fresh()->paid_at->toDateTimeString())->toBe($expectedRecordedAt->toDateTimeString())
        ->and($repairOrder->fresh()->isPaid())->toBeTrue();
});

test('record cash card and check payments through ledger authority', function (string $method) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '50.00',
        'payment_method' => $method,
        'reference' => strtoupper($method).' tender',
    ])->assertRedirect();

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->where('payment_method', $method)
        ->exists())->toBeTrue();
})->with(['cash', 'card', 'check']);

test('partial payment posture surfaces on ro review', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '50.00',
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Partially paid')
        ->assertSee('$100.00')
        ->assertSee('Settlement balance');
});

test('paid posture surfaces when balance is zero', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Paid / ready to close')
        ->assertSee('Eligible to close')
        ->assertDontSee('Record Payment');
});

test('close is blocked before invoice and with balance due', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::Closed->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::Closed->value,
    ])->assertRedirect()
        ->assertSessionHasErrors('lifecycle');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue();
});

test('close is allowed when invoice issued and balance due is zero', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->forceFill(['mileage_out' => 88000])->save();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    // Close must answer how it closed — paid vs lost — not a generic closed state.
    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => 'closed:paid',
        'review_request_sent' => '1',
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->fresh()->close_variant_key)->toBe('paid');
});

test('financial rail renders calculator balance not estimate total as balance due', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '60.00',
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('$90.00')
        ->assertSee('Settlement balance');
});

test('mark paid route requires amount and uses ledger entries', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [])
        ->assertSessionHasErrors(['amount', 'payment_method']);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder->fresh()), [
        'amount' => '150.00',
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect();

    expect((int) RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->sum('amount_cents'))->toBe(15000)
        ->and($repairOrder->fresh()->isPaid())->toBeTrue();
});

test('payment route is rejected before invoice issuance', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder), [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertStatus(422);
});

test('manual deposit requires explicit confirmation flag', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
            'amount' => '25.00',
            'payment_method' => PaymentMethod::Check->value,
        ])
        ->assertSessionHasErrors('deposit_confirmed');

    expect(RepairOrderLedgerEntry::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0);
});

test('additional manual deposit is accepted after suggested deposit is already on file', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(\App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard::class);
    $remainingCents = $guard->remainingSuggestedDepositCents($repairOrder);
    expect($remainingCents)->toBeInt()->toBeGreaterThan(0);

    $remainingDecimal = number_format($remainingCents / 100, 2, '.', '');

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => $remainingDecimal,
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
    ])->assertRedirect();

    $this->from(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->patch(route('operations.repair-orders.deposit.update', $repairOrder->fresh()), [
            'amount' => '25.00',
            'payment_method' => PaymentMethod::Cash->value,
            'deposit_confirmed' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->count())->toBe(2);
});

test('manual deposit form stays available after suggested deposit is satisfied', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderWithSuggestedPartDeposit();
    $guard = app(\App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard::class);
    $remainingCents = $guard->remainingSuggestedDepositCents($repairOrder);

    app(\App\Ark\Operations\Financial\RecordLedgerEntryAction::class)->recordDeposit(
        $repairOrder,
        $remainingCents,
        PaymentMethod::Cash,
    );

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Record deposit in ledger')
        ->assertSee('remaining on this repair')
        ->assertDontSee('Suggested deposit is covered.');
});
