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
        $terminal = TerminalPaymentProgress::payload($updated);
        $canceled = $updated->status === PaymentCaptureAttemptStatus::Cancelled;

        $message = $canceled
            ? 'Payment canceled. No payment was recorded.'
            : 'The reader could not be canceled. The payment is still with the customer.';

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
                'terminal' => $terminal,
            ], $canceled ? 200 : 422);
        }

        $redirect = redirect()->back();

        return $canceled
            ? $redirect->with('status', $message)
            : $redirect->withErrors(['capture' => $message]);
    }
}
