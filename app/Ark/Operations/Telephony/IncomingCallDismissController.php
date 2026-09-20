<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class IncomingCallDismissController
{
    public function __invoke(
        Request $request,
        IncomingCallPopupDismissal $popupDismissal,
        CallSessionQueue $queue,
    ): Response {
        $dismissedSessionId = $request->integer('call_session_id');
        $viewer = $request->user();

        if ($dismissedSessionId > 0) {
            $session = CallSession::query()->find($dismissedSessionId);

            if ($session !== null) {
                $queue->markCallerHandled($session);
            }
        }

        if ($viewer !== null && $dismissedSessionId > 0) {
            $popupDismissal->dismiss($viewer->id, $dismissedSessionId);
        }

        $cached = Cache::get(IncomingCallContextBroadcaster::cacheKey());

        if (! is_array($cached)) {
            return response()->noContent();
        }

        $cachedSessionId = (int) ($cached['call_session_id'] ?? 0);

        if ($dismissedSessionId === 0 || $cachedSessionId === $dismissedSessionId) {
            Cache::forget(IncomingCallContextBroadcaster::cacheKey());
        }

        return response()->noContent();
    }
}
