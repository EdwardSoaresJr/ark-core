<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderSquareDepositInitiateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InitiateSquareDepositAction $initiate,
        PaymentGatewayAttemptPresenter $presenter,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'capture_surface' => ['required', Rule::in([
                PaymentCaptureSurface::Terminal->value,
                PaymentCaptureSurface::Keyed->value,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $surface = PaymentCaptureSurface::from($data['capture_surface']);

        try {
            $attempt = $initiate->execute(
                $repairOrder,
                $surface,
                $request->user(),
                (float) $data['amount'],
            );
        } catch (SquarePaymentRequestException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
            'message' => $surface === PaymentCaptureSurface::Terminal
                ? 'Square terminal checkout sent to reader.'
                : 'Square keyed deposit ready for card entry.',
        ]);
    }
}
