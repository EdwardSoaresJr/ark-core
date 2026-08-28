<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Payments\InitiateSquarePaymentAction;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGatewayAttemptPresenter;
use App\Ark\Operations\Payments\SquarePaymentRequestException;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileRepairOrderSquarePaymentInitiateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        InitiateSquarePaymentAction $initiate,
        PaymentGatewayAttemptPresenter $presenter,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        abort_unless($access->canRecordPayment($request->user(), $repairOrder), 403);

        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'capture_surface' => ['required', Rule::in([
                PaymentCaptureSurface::Terminal->value,
            ])],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        try {
            $attempt = $initiate->execute(
                $repairOrder,
                PaymentCaptureSurface::Terminal,
                $request->user(),
                $amount,
            );
        } catch (SquarePaymentRequestException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
            'message' => 'Square terminal checkout sent to reader.',
        ]);
    }
}
