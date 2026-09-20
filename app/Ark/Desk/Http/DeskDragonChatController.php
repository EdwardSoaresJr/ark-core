<?php

namespace App\Ark\Desk\Http;

use App\Ark\Dragon\Agent\ChatDragonAgentAction;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Ark\Mobile\MobileIncomingCallContextProjection;
use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskDragonChatController
{
    public function __invoke(
        Request $request,
        ChatDragonAgentAction $action,
        MobileIncomingCallContextProjection $calls,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'conversation_id' => ['nullable', 'uuid'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        $situation = 'ARK Desk. Advisor: '.$user->name.'. Client: ark_desk.';
        if (isset($data['call_session_id'])) {
            $session = CallSession::query()->find((int) $data['call_session_id']);
            if ($session !== null) {
                $ctx = $calls->forCallSession($session);
                $situation .= ' Active call '.$session->id.'.';
                if (! empty($ctx['customer']['name'])) {
                    $situation .= ' Customer: '.$ctx['customer']['name'].'.';
                } else {
                    $situation .= ' Unknown caller '.$ctx['phone'].'.';
                }
                if (! empty($ctx['repair_order']['repair_order_id'])) {
                    $situation .= ' RO '.$ctx['repair_order']['repair_order_id'].'.';
                }
            }
        }
        if (isset($data['repair_order_id'])) {
            $situation .= ' Repair order '.$data['repair_order_id'].'.';
        }

        try {
            $result = $action->handle(
                $user,
                $data['message'],
                $data['conversation_id'] ?? null,
                $situation,
            );
        } catch (DragonProviderUnavailable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'provider_unavailable',
                'message' => 'Dragon is unavailable. Calls, tasks, and customer context still work.',
            ], 503);
        }

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }
}
