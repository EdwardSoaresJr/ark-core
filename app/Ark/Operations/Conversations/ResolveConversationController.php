<?php

namespace App\Ark\Operations\Conversations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResolveConversationController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationPosture $posture,
    ): JsonResponse|RedirectResponse {
        $posture->resolve($conversation, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Conversation resolved.');
    }
}
