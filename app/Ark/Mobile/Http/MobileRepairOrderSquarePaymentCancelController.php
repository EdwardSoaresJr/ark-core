<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Payments\CancelSquarePaymentAttemptAction;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptPresenter;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderSquarePaymentCancelController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentGatewayAttempt $attempt,
        MobileStaffAccess $access,
        CancelSquarePaymentAttemptAction $cancel,
        PaymentGatewayAttemptPresenter $presenter,
    ): JsonResponse {
        abort_unless($access->canRecordPayment($request->user(), $repairOrder), 403);
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $attempt = $cancel->execute($attempt);

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
            'message' => 'Square payment attempt canceled.',
        ]);
    }
}
