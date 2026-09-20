<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileInboundCallProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class MobileTelephonyVoiceAnswerController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileInboundCallProjection $inboundCall,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        $data = $request->validate([
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'claim_active' => ['sometimes', 'boolean'],
        ]);

        $session = $this->resolveSession(
            user: $request->user(),
            callSessionId: isset($data['call_session_id']) ? (int) $data['call_session_id'] : null,
            claimActive: (bool) ($data['claim_active'] ?? false),
            inboundCall: $inboundCall,
        );

        if ($session->owned_by_user_id === null) {
            $session->forceFill([
                'owned_by_user_id' => $request->user()->id,
                'owned_at' => now(),
            ])->save();
        }

        return response()->json([
            'answered' => true,
            'call_session_id' => $session->id,
        ]);
    }

    private function resolveSession(
        User $user,
        ?int $callSessionId,
        bool $claimActive,
        MobileInboundCallProjection $inboundCall,
    ): CallSession {
        if ($callSessionId !== null) {
            return CallSession::query()->findOrFail($callSessionId);
        }

        if ($claimActive) {
            $active = $inboundCall->activeForUser($user);

            if ($active === null) {
                throw ValidationException::withMessages([
                    'claim_active' => 'No active inbound call is ringing your mobile extension.',
                ]);
            }

            return CallSession::query()->findOrFail($active['call_session_id']);
        }

        throw ValidationException::withMessages([
            'call_session_id' => 'A call session is required.',
        ]);
    }
}
