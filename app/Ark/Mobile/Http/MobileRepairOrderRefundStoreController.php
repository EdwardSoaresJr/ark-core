<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderRefundStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        RecordLedgerEntryAction $ledger,
        EstimateTotalsCalculator $totalsCalculator,
        NotifyRepairOrderFinancialChange $notifyFinancialChange,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        abort_unless($access->canRecordRefund($request->user(), $repairOrder), 403);

        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $amountCents = $totalsCalculator->unitPriceCents($data['amount']);

        $ledger->recordRefund(
            $repairOrder,
            $amountCents,
            $request->user(),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
        );

        $repairOrder = $repairOrder->fresh();
        $notifyFinancialChange->notify($repairOrder, reason: 'refund_recorded', actor: $request->user());

        return response()->json([
            'message' => 'Refund recorded.',
            'repair_order_id' => $repairOrder->repair_order_id,
            'payment_status' => $repairOrder->paymentStatus()->value,
            'balance_due_cents' => $repairOrder->balanceDue()->balanceDueCents,
        ]);
    }
}
