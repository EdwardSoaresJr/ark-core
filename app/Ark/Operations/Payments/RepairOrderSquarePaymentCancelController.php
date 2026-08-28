<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;

class RepairOrderSquarePaymentCancelController
{
    public function __invoke(
        RepairOrder $repairOrder,
        PaymentGatewayAttempt $attempt,
        CancelSquarePaymentAttemptAction $cancel,
        PaymentGatewayAttemptPresenter $presenter,
    ): JsonResponse {
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $attempt = $cancel->execute($attempt);

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
            'message' => 'Square payment attempt canceled.',
        ]);
    }
}
