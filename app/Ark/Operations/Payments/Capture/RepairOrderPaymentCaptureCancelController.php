<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderPaymentCaptureCancelController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentCaptureAttempt $attempt,
        InitiatePaymentCaptureAction $initiate,
    ): RedirectResponse|JsonResponse {
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $updated = $initiate->cancelFromCloud($attempt);

        $message = match ($updated->status) {
            PaymentCaptureAttemptStatus::Cancelled => 'Payment request cancelled. No ledger payment was recorded.',
            PaymentCaptureAttemptStatus::Succeeded => $updated->hasLedgerEntry()
                ? 'The terminal already took the payment. It is recorded.'
                : 'The terminal already completed.',
            PaymentCaptureAttemptStatus::Failed => 'Payment capture failed. No ledger payment recorded.',
            PaymentCaptureAttemptStatus::ReconciliationRequired => 'Still needs reconciliation.',
            PaymentCaptureAttemptStatus::Pending, PaymentCaptureAttemptStatus::Accepted => 'The terminal is still holding the request. Cancel again if it stays up.',
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
