<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationsWorkspaceContextBuilder;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Send payment portal link on the active communications thread.
 */
class SendConversationPaymentLinkController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        CommunicationsWorkspaceContextBuilder $contextBuilder,
        SendPaymentDeliveryAction $send,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        $context = $contextBuilder->conversationComposerContext($conversation);
        $repairOrder = $context['repair_order'] ?? null;

        if (! $repairOrder instanceof RepairOrder) {
            return response()->json(['message' => 'No repair order is linked to this conversation yet. Start intake first.'], 422);
        }

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
                $conversation,
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
