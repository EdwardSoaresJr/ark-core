<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobilePaymentCaptureAttemptPresenter;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Payments\Capture\InitiatePaymentCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MobileRepairOrderPaymentCaptureStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        InitiatePaymentCaptureAction $initiate,
        EstimateTotalsCalculator $totals,
        MobilePaymentCaptureAttemptPresenter $presenter,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'context_kind' => ['required', Rule::in(['payment', 'deposit'])],
            'capture_method' => ['required', Rule::in(['terminal'])],
            'device_ref' => ['required', 'string', 'max:128'],
            'stub_scenario' => ['nullable', 'string', 'max:64'],
        ]);

        $kind = PaymentCaptureContextKind::from($data['context_kind']);
        abort_unless(
            $kind === PaymentCaptureContextKind::Payment
                ? $access->canRecordPayment($request->user(), $repairOrder)
                : $access->canRecordDeposit($request->user(), $repairOrder),
            403,
        );

        $concurrency->guardWithoutHolding($request, $repairOrder);

        if (blank($data['device_ref'])) {
            return response()->json(['message' => 'Select a terminal device.'], 422);
        }

        try {
            $result = $initiate->execute($repairOrder, $request->user(), [
                'amount_cents' => $totals->unitPriceCents($data['amount']),
                'context_kind' => $kind,
                'capture_method' => PaymentCaptureMethod::Terminal,
                'device_ref' => $data['device_ref'],
                'stub_scenario' => app()->environment('local', 'testing') ? ($data['stub_scenario'] ?? null) : null,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
            ], 422);
        }

        $attempt = $result['attempt'];

        return response()->json([
            'message' => match ($attempt->status->value) {
                'succeeded' => 'Payment captured and recorded.',
                'pending', 'accepted' => 'Payment is processing.',
                'reconciliation_required' => 'Payment needs reconciliation - do not charge the same amount again until resolved.',
                'failed' => 'Payment capture failed. No ledger payment was recorded.',
                'cancelled' => 'Payment capture cancelled. No ledger payment was recorded.',
                default => 'Payment capture updated.',
            },
            'attempt' => $presenter->forAttempt($attempt),
        ]);
    }
}
