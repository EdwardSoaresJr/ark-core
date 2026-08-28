<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileCallMarkHandledController
{
    public function __invoke(
        Request $request,
        CallSession $callSession,
        MobileStaffAccess $access,
        CallSessionQueue $queue,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        $queue->markCallerHandled($callSession);

        return response()->json([
            'handled' => true,
            'call_session_id' => $callSession->id,
        ]);
    }
}
