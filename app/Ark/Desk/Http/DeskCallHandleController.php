<?php

namespace App\Ark\Desk\Http;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskCallHandleController
{
    public function __invoke(Request $request, CallSession $call, CallSessionQueue $queue): JsonResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $queue->markCallerHandled($call);

        return response()->json([
            'handled' => true,
            'call_session_id' => $call->id,
        ]);
    }
}
