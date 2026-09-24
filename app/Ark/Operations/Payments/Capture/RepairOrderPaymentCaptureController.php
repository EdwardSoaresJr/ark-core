<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderPaymentCaptureController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InitiatePaymentCaptureAction $initiate,
        EstimateTotalsCalculator $totals,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse|JsonResponse {
        $concurrency->guardWithoutHolding($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'context_kind' => ['required', Rule::in(['payment', 'deposit'])],
            'capture_method' => ['required', Rule::in(['terminal', 'keyed'])],
            'device_ref' => ['nullable', 'string', 'max:128'],
            'source_token' => ['nullable', 'string', 'max:191'],
            'stub_scenario' => ['nullable', 'string', 'max:64'],
        ]);

        if ($data['capture_method'] === 'terminal' && blank($data['device_ref'] ?? null)) {
            return $this->captureRejected($request, 'Select a terminal device.');
        }

        if ($data['capture_method'] === 'keyed' && blank($data['source_token'] ?? null)) {
            return $this->captureRejected($request, 'Card details are required.');
        }

        $result = $initiate->execute($repairOrder, $request->user(), [
            'amount_cents' => $totals->unitPriceCents($data['amount']),
            'context_kind' => PaymentCaptureContextKind::from($data['context_kind']),
            'capture_method' => PaymentCaptureMethod::from($data['capture_method']),
            'device_ref' => $data['device_ref'] ?? null,
            'source_token' => $data['source_token'] ?? null,
            'stub_scenario' => $data['stub_scenario'] ?? null,
        ]);

        $attempt = $result['attempt'];
        $message = match ($attempt->status) {
            PaymentCaptureAttemptStatus::Succeeded => 'Payment captured and recorded.',
            PaymentCaptureAttemptStatus::Pending, PaymentCaptureAttemptStatus::Accepted => 'Payment is processing. Refresh or check status if it stays open.',
            PaymentCaptureAttemptStatus::ReconciliationRequired => 'Payment needs reconciliation - do not charge the same amount again until resolved.',
            PaymentCaptureAttemptStatus::Failed => 'Payment capture failed. No ledger payment was recorded.',
            PaymentCaptureAttemptStatus::Cancelled => 'Payment capture cancelled. No ledger payment was recorded.',
        };

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'attempt' => $this->presentAttempt($attempt),
            ]);
        }

        return redirect()->back()->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAttempt(PaymentCaptureAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'public_id' => $attempt->public_id,
            'status' => $attempt->status->value,
            'amount_cents' => $attempt->amount_cents,
            'capture_method' => $attempt->capture_method->value,
            'context_kind' => $attempt->context_kind->value,
            'idempotency_key' => $attempt->idempotency_key,
            'device_ref' => $attempt->device_ref,
        ];
    }

    private function captureRejected(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['capture' => $message])->withInput();
    }
}
