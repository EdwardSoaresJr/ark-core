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
 * Send deposit portal link on the active communications thread.
 */
class SendConversationDepositRequestLinkController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        CommunicationsWorkspaceContextBuilder $contextBuilder,
        SendDepositRequestDeliveryAction $send,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        $context = $contextBuilder->conversationComposerContext($conversation);
        $repairOrder = $context['repair_order'] ?? null;

        if (! $repairOrder instanceof RepairOrder) {
            return response()->json(['message' => 'No repair order is linked to this conversation yet. Start intake first.'], 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
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
            return response()->json(['message' => 'You do not have permission to email deposit requests.'], 403);
        }

        try {
            $result = $send->execute(
                $repairOrder,
                $request->user(),
                $mode,
                $data['amount'],
                $data['email'] ?? null,
                $conversation,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make($result['messages'], array_filter([
            'deposit_url' => $result['deposit_url'],
            'amount_display' => $result['amount_display'],
        ], fn (mixed $value): bool => $value !== null));
    }
}
