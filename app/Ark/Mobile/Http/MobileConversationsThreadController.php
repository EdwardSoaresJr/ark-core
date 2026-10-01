<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileConversationsProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Conversations\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileConversationsThreadController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        MobileStaffAccess $access,
        MobileConversationsProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewConversation($request->user(), $conversation), 403);

        return response()->json([
            'thread' => $projection->threadForConversation($request->user(), $conversation),
        ]);
    }
}
