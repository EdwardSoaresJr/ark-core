<?php

use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardCardProjection;
use Tests\TestCase;


test('workboard footnote mirror paid ledger unpaid shows balance due not paid', function () {
    [$footnote, $nextAction] = workboardPaymentFootnoteCase(
        mirror: RepairOrderPaymentStatus::Paid,
        ledgerPaid: false,
    );

    expect($footnote)->toBe('Balance due · collect before close')
        ->and($footnote)->not->toContain('Paid')
        ->and($nextAction)->toBe('Collect balance')
        ->and($footnote)->not->toMatch('/Paid.*collect|collect.*Paid/i');
});

test('workboard footnote mirror unpaid ledger paid shows ready to close not collect', function () {
    [$footnote, $nextAction] = workboardPaymentFootnoteCase(
        mirror: RepairOrderPaymentStatus::Unpaid,
        ledgerPaid: true,
    );

    expect($footnote)->toBe('Paid · ready to close')
        ->and($footnote)->not->toContain('collect')
        ->and($nextAction)->toBe('Close paid')
        ->and($footnote)->not->toMatch('/Paid.*collect|collect.*Paid/i');
});

test('workboard footnote both unpaid keeps balance due collection wording', function () {
    [$footnote, $nextAction] = workboardPaymentFootnoteCase(
        mirror: RepairOrderPaymentStatus::Unpaid,
        ledgerPaid: false,
    );

    expect($footnote)->toBe('Balance due · collect before close')
        ->and($nextAction)->toBe('Collect balance');
});

test('workboard footnote both paid keeps ready to close wording', function () {
    [$footnote, $nextAction] = workboardPaymentFootnoteCase(
        mirror: RepairOrderPaymentStatus::Paid,
        ledgerPaid: true,
    );

    expect($footnote)->toBe('Paid · ready to close')
        ->and($nextAction)->toBe('Close paid');
});

test('workboard footnote reuses projection isPaid from batch balances', function () {
    $repairOrder = workboardFootnoteRepairOrder(RepairOrderPaymentStatus::Paid);
    $projection = WorkboardCardProjection::forRepairOrder(
        $repairOrder,
        totals: null,
        balances: [$repairOrder->id => workboardFootnoteBalance(false)],
    );

    expect($projection->isPaid)->toBeFalse()
        ->and($projection->footnoteContextFor($repairOrder, RepairOrderStatus::Invoiced))
        ->toBe('Balance due · collect before close')
        ->and($projection->nextAction($repairOrder, RepairOrderStatus::Invoiced))
        ->toBe('Collect balance');
});

/**
 * @return array{0: string, 1: string}
 */
function workboardPaymentFootnoteCase(RepairOrderPaymentStatus $mirror, bool $ledgerPaid): array
{
    $repairOrder = workboardFootnoteRepairOrder($mirror);
    $projection = WorkboardCardProjection::forRepairOrder(
        $repairOrder,
        totals: null,
        balances: [$repairOrder->id => workboardFootnoteBalance($ledgerPaid)],
    );

    return [
        (string) $projection->footnoteContextFor($repairOrder, RepairOrderStatus::ReadyPickup),
        $projection->nextAction($repairOrder, RepairOrderStatus::ReadyPickup),
    ];
}

function workboardFootnoteRepairOrder(RepairOrderPaymentStatus $mirror): RepairOrder
{
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Board',
        'last_name' => 'Footnote',
        'phone' => '555-'.random_int(1000, 9999),
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'Escape',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyPickup,
        'concern_summary' => 'Workboard footnote payment truth.',
        'payment_status' => $mirror,
        'paid_at' => $mirror === RepairOrderPaymentStatus::Paid ? now() : null,
    ])->fresh();
}

function workboardFootnoteBalance(bool $paid): BalanceDueResult
{
    return new BalanceDueResult(
        hasIssuedInvoice: true,
        invoiceTotalCents: 15000,
        depositsAppliedCents: 0,
        paymentsAppliedCents: $paid ? 15000 : 0,
        refundsAppliedCents: 0,
        adjustmentsCents: 0,
        creditsAppliedCents: 0,
        writeOffsCents: 0,
        balanceDueCents: $paid ? 0 : 15000,
        unappliedDepositsCents: 0,
        invoiceStatus: $paid ? InvoiceStatus::Paid : InvoiceStatus::Issued,
    );
}
