<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairOrderSquarePaymentKeyedCompleteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentGatewayAttempt $attempt,
        CompleteSquareKeyedPaymentAction $complete,
        PaymentGatewayAttemptPresenter $presenter,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        $concurrency->guard($request, $repairOrder);

        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);
        abort_unless($attempt->capture_surface === PaymentCaptureSurface::Keyed, 422);

        $data = $request->validate([
            'source_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $attempt = $complete->execute($attempt, $data['source_id'], $request->user());
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'attempt' => $presenter->forAttempt($attempt->fresh()),
            ], 422);
        }

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
            'message' => $attempt->collectsDeposit()
                ? 'Square deposit recorded.'
                : 'Square payment recorded.',
        ]);
    }
}
