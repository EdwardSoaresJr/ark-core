<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationsWorkspaceContextBuilder;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Send estimate portal link on the active communications thread.
 */
class SendConversationEstimateLinkController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        CommunicationsWorkspaceContextBuilder $contextBuilder,
        SendEstimateLinkAction $sendEstimate,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        $context = $contextBuilder->conversationComposerContext($conversation);
        $repairOrder = $context['repair_order'] ?? null;

        if (! $repairOrder instanceof RepairOrder) {
            return response()->json(['message' => 'No repair order is linked to this conversation yet. Start intake first.'], 422);
        }

        try {
            $result = $sendEstimate->execute($repairOrder, $request->user(), $conversation);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make([$result['message']], array_filter([
            'estimate_url' => $result['url'],
            'token_reused' => $result['token_reused'],
            'awaiting_approval' => $result['awaiting_approval'],
        ], fn (mixed $value): bool => $value !== null));
    }
}
