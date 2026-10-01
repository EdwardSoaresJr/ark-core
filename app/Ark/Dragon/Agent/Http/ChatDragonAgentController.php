<?php

namespace App\Ark\Dragon\Agent\Http;

use App\Ark\Dragon\Agent\ChatDragonAgentAction;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatDragonAgentController
{
    public function __invoke(Request $request, ChatDragonAgentAction $action): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $result = $action->handle(
                $user,
                $data['message'],
                $data['conversation_id'] ?? null,
            );
        } catch (DragonProviderUnavailable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'provider_unavailable',
                'message' => 'The hosted Dragon model is unavailable. Shop operations in ARK and Shop Glass still work without it.',
            ], 503);
        }

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }
}
