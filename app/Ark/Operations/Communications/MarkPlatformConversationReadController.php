<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\Communications\ArkCommunicationsClient;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkPlatformConversationReadController
{
    public function __invoke(
        Request $request,
        string $platformConversation,
        ArkCommunicationsClient $client,
    ): JsonResponse {
        abort_unless(ManagedCommunicationsGate::platformInbox(), 404);

        $user = $request->user();
        if ($user === null) {
            return response()->json(['ok' => false], 401);
        }

        $result = $client->markRead($platformConversation, (int) $user->id);

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
        ]);
    }
}
