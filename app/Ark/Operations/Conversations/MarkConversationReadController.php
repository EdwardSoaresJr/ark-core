<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Portal\PortalCustomerActivityBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarkConversationReadController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationReadTracker $readTracker,
    ): JsonResponse|RedirectResponse {
        $through = $conversation->messages()
            ->max('occurred_at');

        $readTracker->markRead(
            $conversation,
            $request->user(),
            $through ? Carbon::parse($through) : now(),
        );

        app(PortalCustomerActivityBroadcaster::class)->clearForConversation((int) $conversation->id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('operations.index');
    }
}
