<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileConversationInternalNoteStoreController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MobileStaffAccess $access,
        ConversationRecorder $recorder,
    ): JsonResponse {
        abort_unless($access->canViewConversation($request->user(), $conversation), 403);
        abort_unless($access->canRecordInternalNote($request->user()), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $recorder->recordInternalNote($conversation, $request->user(), $data['body']);

        return response()->json([
            'message_id' => $message->id,
        ], 201);
    }
}
