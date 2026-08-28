<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIncomingCallContextProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileIncomingCallContextController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileIncomingCallContextProjection $projection,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessShopCommunications($user), 403);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
        ]);

        if (isset($validated['call_session_id'])) {
            $session = CallSession::query()->findOrFail((int) $validated['call_session_id']);

            return response()->json($projection->forCallSession($session));
        }

        $phone = trim((string) ($validated['phone'] ?? ''));

        if ($phone === '') {
            return response()->json([
                'message' => 'Provide phone or call_session_id.',
            ], 422);
        }

        return response()->json($projection->forPhone($phone));
    }
}
