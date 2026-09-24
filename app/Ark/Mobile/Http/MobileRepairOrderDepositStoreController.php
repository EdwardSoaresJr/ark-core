<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\ManualDepositSubmission;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileRepairOrderDepositStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderConcurrency $concurrency,
        ManualDepositSubmission $deposits,
    ): JsonResponse {
        abort_unless($access->canRecordDeposit($request->user(), $repairOrder), 403);

        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::in([
                PaymentMethod::Cash->value,
                PaymentMethod::Card->value,
                PaymentMethod::Check->value,
            ])],
            'reference' => ['nullable', 'string', 'max:255'],
            FinancialSubmissionIntentGate::FIELD => ['nullable', 'uuid'],
        ]);

        $deposits->execute(
            $repairOrder,
            $request->user(),
            $data[FinancialSubmissionIntentGate::FIELD] ?? null,
            $totalsCalculator->unitPriceCents($data['amount']),
            PaymentMethod::from($data['payment_method']),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            broadcastFinancialChange: false,
        );

        $repairOrder = $repairOrder->fresh();

        return response()->json([
            'message' => 'Deposit recorded.',
            'repair_order_id' => $repairOrder->repair_order_id,
            'unapplied_deposits_cents' => $repairOrder->balanceDue()->unappliedDepositsCents,
        ]);
    }
}
