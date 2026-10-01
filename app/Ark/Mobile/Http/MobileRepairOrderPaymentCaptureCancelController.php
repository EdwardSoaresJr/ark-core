<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobilePaymentCaptureAttemptPresenter;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Payments\Capture\InitiatePaymentCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderPaymentCaptureCancelController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        PaymentCaptureAttempt $attempt,
        MobileStaffAccess $access,
        InitiatePaymentCaptureAction $initiate,
        MobilePaymentCaptureAttemptPresenter $presenter,
    ): JsonResponse {
        abort_unless(
            $access->canViewRepairOrder($request->user(), $repairOrder)
                && $request->user()->can(ArkCapability::RepairOrdersManage->value),
            403,
        );
        abort_unless($attempt->repair_order_id === $repairOrder->id, 404);

        $updated = $initiate->cancelFromCloud($attempt);

        return response()->json([
            'message' => 'Payment capture cancelled.',
            'attempt' => $presenter->forAttempt($updated),
        ]);
    }
}
