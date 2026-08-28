<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairOrderSquarePaymentPollController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentGatewayAttempt $attempt,
        PollSquareTerminalCheckoutAction $poll,
        PaymentGatewayAttemptPresenter $presenter,
    ): JsonResponse {
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $attempt = $poll->execute($attempt, $request->user());

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
        ]);
    }
}
