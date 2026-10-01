<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarkCallSessionWorkedController
{
    public function __invoke(Request $request, CallSession $callSession, CallSessionQueue $queue): JsonResponse|RedirectResponse
    {
        $clearedCount = $queue->markCallerHandled($callSession);

        if ($request->expectsJson()) {
            return response()->json([
                'worked' => true,
                'call_session_id' => $callSession->id,
                'cleared_count' => $clearedCount,
            ]);
        }

        return redirect()->route('operations.index');
    }
}
