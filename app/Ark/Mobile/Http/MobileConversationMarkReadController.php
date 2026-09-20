<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationReadTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class MobileConversationMarkReadController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MobileStaffAccess $access,
        ConversationReadTracker $readTracker,
    ): JsonResponse {
        abort_unless($access->canViewConversation($request->user(), $conversation), 403);

        $through = $conversation->messages()
            ->max('occurred_at');

        $read = $readTracker->markRead(
            $conversation,
            $request->user(),
            $through ? Carbon::parse($through) : now(),
        );

        return response()->json([
            'ok' => true,
            'read_through_at' => $read->read_through_at?->toIso8601String(),
            'unread_count' => $readTracker->unreadInboundCount($conversation, $request->user()),
        ]);
    }
}
