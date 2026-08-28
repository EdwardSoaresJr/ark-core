<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());
});

test('staff can void a payment from the financial rail', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $entry = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->firstOrFail();

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->delete(route('operations.repair-orders.ledger-entries.destroy', [$repairOrder, $entry]))
        ->assertRedirect();

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->balanceDueCents)->toBe(15000)
        ->and($balance->invoiceStatus)->toBe(InvoiceStatus::Issued)
        ->and($entry->fresh()->voided_at)->not->toBeNull();
});

test('staff can record a refund from the financial rail', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.refund.store', $repairOrder), [
            'amount' => '50.00',
            'reference' => 'Card refund',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $balance = app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh());

    expect($balance->balanceDueCents)->toBe(5000)
        ->and(RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Refund)
            ->exists())->toBeTrue();
});

test('financial rail exposes void and refund controls after payment', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Void entry')
        ->assertSee('Record Refund');
});
