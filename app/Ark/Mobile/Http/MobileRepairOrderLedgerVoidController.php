<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Financial\VoidLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderLedgerVoidController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLedgerEntry $entry,
        MobileStaffAccess $access,
        VoidLedgerEntryAction $void,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        abort_unless($entry->repair_order_id === $repairOrder->id, 404);
        abort_unless($access->canVoidLedgerEntry($request->user(), $repairOrder, $entry), 403);

        $concurrency->guard($request, $repairOrder);

        $void->execute($entry, $request->user());

        $repairOrder = $repairOrder->fresh();

        return response()->json([
            'message' => 'Ledger entry voided.',
            'repair_order_id' => $repairOrder->repair_order_id,
            'payment_status' => $repairOrder->paymentStatus()->value,
            'balance_due_cents' => $repairOrder->balanceDue()->balanceDueCents,
        ]);
    }
}
