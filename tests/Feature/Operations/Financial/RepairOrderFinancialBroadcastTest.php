<?php

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrderFinancialChanged;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Event;

test('portal and square payments broadcast financial changes when broadcasting is enabled', function () {
    config(['broadcasting.default' => 'log']);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Event::fake([RepairOrderFinancialChanged::class]);

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        2500,
        PaymentMethod::Card,
    );

    app(NotifyRepairOrderFinancialChange::class)->notify(
        $repairOrder->fresh(),
        reason: 'payment_received',
    );

    Event::assertDispatched(RepairOrderFinancialChanged::class, function (RepairOrderFinancialChanged $event) use ($repairOrder): bool {
        return $event->repairOrder->repair_order_id === $repairOrder->repair_order_id
            && $event->broadcastAs() === 'financial.changed'
            && $event->broadcastWith()['reason'] === 'payment_received';
    });
});

test('financial change broadcasts are skipped when broadcasting is disabled', function () {
    config(['broadcasting.default' => 'null']);

    $repairOrder = financialCloseoutRepairOrder();

    Event::fake([RepairOrderFinancialChanged::class]);

    app(NotifyRepairOrderFinancialChange::class)->notify($repairOrder, reason: 'payment_received');

    Event::assertNotDispatched(RepairOrderFinancialChanged::class);
});
