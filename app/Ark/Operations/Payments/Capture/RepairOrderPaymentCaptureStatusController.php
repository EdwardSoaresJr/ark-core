<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderPaymentCaptureStatusController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentCaptureAttempt $attempt,
        InitiatePaymentCaptureAction $initiate,
    ): RedirectResponse|JsonResponse {
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $updated = $initiate->refreshFromCloud($attempt);

        $message = match ($updated->status) {
            PaymentCaptureAttemptStatus::Succeeded => $updated->hasLedgerEntry()
                ? 'Capture confirmed and recorded.'
                : 'Capture succeeded.',
            PaymentCaptureAttemptStatus::ReconciliationRequired => 'Still needs reconciliation.',
            PaymentCaptureAttemptStatus::Pending, PaymentCaptureAttemptStatus::Accepted => 'Still processing.',
            PaymentCaptureAttemptStatus::Failed => 'Capture failed. No ledger payment recorded.',
            PaymentCaptureAttemptStatus::Cancelled => 'Capture cancelled.',
        };

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'attempt' => [
                    'id' => $updated->id,
                    'public_id' => $updated->public_id,
                    'status' => $updated->status->value,
                    'amount_cents' => $updated->amount_cents,
                    'ledger_entry_id' => $updated->ledger_entry_id,
                ],
            ]);
        }

        return redirect()->back()->with('status', $message);
    }
}
