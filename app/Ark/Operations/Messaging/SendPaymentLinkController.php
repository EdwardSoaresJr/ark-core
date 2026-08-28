<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SendPaymentLinkController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SendPaymentDeliveryAction $send,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        $data = $request->validate([
            'delivery' => ['nullable', Rule::enum(OutboundDeliveryMode::class)],
            'email' => [
                'exclude_unless:delivery,email,both',
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
            ],
        ]);

        $mode = OutboundDeliveryMode::tryFrom((string) ($data['delivery'] ?? OutboundDeliveryMode::Sms->value))
            ?? OutboundDeliveryMode::Sms;

        if ($mode->includesEmail() && ! $request->user()?->can(ArkCapability::RepairOrdersManage->value)) {
            return response()->json(['message' => 'You do not have permission to email payment links.'], 403);
        }

        try {
            $result = $send->execute(
                $repairOrder,
                $request->user(),
                $mode,
                $data['email'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make($result['messages'], array_filter([
            'payment_url' => $result['payment_url'],
            'balance_due_display' => $result['balance_due_display'],
        ], fn (mixed $value): bool => $value !== null));
    }
}
