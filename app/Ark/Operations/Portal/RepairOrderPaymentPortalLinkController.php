<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Messaging\PaymentPortalLinkContext;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RepairOrderPaymentPortalLinkController
{
    public function __invoke(
        RepairOrder $repairOrder,
        PaymentPortalLinkContext $paymentLink,
    ): JsonResponse {
        $repairOrder->loadMissing('customer');

        try {
            $context = $paymentLink->forRepairOrder($repairOrder);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'url' => $context['url'],
            'balance_due_display' => $context['balance_due_display'],
        ]);
    }
}
