<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderSquarePaymentInitiateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InitiateSquarePaymentAction $initiate,
        PaymentGatewayAttemptPresenter $presenter,
        RepairOrderConcurrency $concurrency,
        EstimateTotalsCalculator $totalsCalculator,
    ): JsonResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'capture_surface' => ['required', Rule::in([
                PaymentCaptureSurface::Terminal->value,
                PaymentCaptureSurface::Keyed->value,
            ])],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $surface = PaymentCaptureSurface::from($data['capture_surface']);
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        try {
            $attempt = $initiate->execute(
                $repairOrder,
                $surface,
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
            'message' => $surface === PaymentCaptureSurface::Terminal
                ? 'Square terminal checkout sent to reader.'
                : 'Square keyed payment ready for card entry.',
        ]);
    }
}
