<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLedgerDepositRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileRepairOrderDepositStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        RepairOrderLedgerDepositRecorder $deposits,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderDepositRecordingGuard $depositGuard,
        RepairOrderConcurrency $concurrency,
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
        ]);

        $amountCents = $totalsCalculator->unitPriceCents($data['amount']);
        $depositGuard->validateAmount($repairOrder, $amountCents);

        $deposits->record(
            $repairOrder,
            $amountCents,
            PaymentMethod::from($data['payment_method']),
            $request->user(),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
        );

        $repairOrder = $repairOrder->fresh();

        return response()->json([
            'message' => 'Deposit recorded.',
            'repair_order_id' => $repairOrder->repair_order_id,
            'unapplied_deposits_cents' => $repairOrder->balanceDue()->unappliedDepositsCents,
        ]);
    }
}
