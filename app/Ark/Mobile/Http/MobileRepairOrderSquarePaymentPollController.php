<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptPresenter;
use App\Ark\Operations\Payments\PollSquareTerminalCheckoutAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderSquarePaymentPollController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentGatewayAttempt $attempt,
        MobileStaffAccess $access,
        PollSquareTerminalCheckoutAction $poll,
        PaymentGatewayAttemptPresenter $presenter,
    ): JsonResponse {
        abort_unless($access->canRecordPayment($request->user(), $repairOrder), 403);
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $attempt = $poll->execute($attempt, $request->user());

        return response()->json([
            'attempt' => $presenter->forAttempt($attempt),
        ]);
    }
}
