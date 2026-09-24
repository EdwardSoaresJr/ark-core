<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\ManualPaymentSubmission;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentPaidAt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileRepairOrderPaymentStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderConcurrency $concurrency,
        FinancialSubmissionIntentGate $intents,
        ManualPaymentSubmission $payments,
    ): JsonResponse {
        abort_unless($access->canRecordPayment($request->user(), $repairOrder), 403);

        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::in([
                PaymentMethod::Cash->value,
                PaymentMethod::Card->value,
                PaymentMethod::Check->value,
            ])],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            FinancialSubmissionIntentGate::FIELD => ['nullable', 'uuid'],
        ]);

        $payments->execute(
            $repairOrder,
            $request->user(),
            $data[FinancialSubmissionIntentGate::FIELD] ?? null,
            $totalsCalculator->unitPriceCents($data['amount']),
            PaymentMethod::from($data['payment_method']),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            $intents->paidOn($data['paid_at'] ?? null),
            RepairOrderPaymentPaidAt::fromDateInput($data['paid_at'] ?? null),
        );

        $repairOrder = $repairOrder->fresh();

        return response()->json([
            'message' => 'Payment recorded.',
            'repair_order_id' => $repairOrder->repair_order_id,
            'payment_status' => $repairOrder->paymentStatus()->value,
            'balance_due_cents' => $repairOrder->balanceDue()->balanceDueCents,
        ]);
    }
}
