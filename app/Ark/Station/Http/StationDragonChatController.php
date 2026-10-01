<?php

namespace App\Ark\Station\Http;

use App\Ark\Dragon\Agent\ChatDragonAgentAction;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Ark\Station\StationDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StationDragonChatController
{
    public function __invoke(Request $request, ChatDragonAgentAction $action): JsonResponse
    {
        $token = $request->attributes->get(AuthenticateStationDevice::REQUEST_ATTR);
        abort_unless($token instanceof StationDeviceToken, 401);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        try {
            $result = $action->handleForStation(
                $token,
                $data['message'],
                $data['conversation_id'] ?? null,
            );
        } catch (DragonProviderUnavailable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'provider_unavailable',
                'message' => 'Dragon is unavailable. Shop Glass still shows live ARK work.',
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'conversation_id' => $result['conversation_id'],
            'reply' => $result['reply'],
            'source' => $result['source'],
        ]);
    }
}
