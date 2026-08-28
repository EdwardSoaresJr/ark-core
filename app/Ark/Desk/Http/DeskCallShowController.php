<?php

namespace App\Ark\Desk\Http;

use App\Ark\Mobile\MobileIncomingCallContextProjection;
use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskCallShowController
{
    public function __invoke(
        Request $request,
        CallSession $call,
        MobileIncomingCallContextProjection $projection,
    ): JsonResponse {
        abort_unless($request->user() instanceof User, 401);

        $payload = $projection->forCallSession($call);
        unset($payload['deep_link'], $payload['routes']);
        $payload['post_call'] = [
            'ready' => false,
            'summary' => null,
            'suggested_follow_up' => null,
        ];

        return response()->json($payload);
    }
}
