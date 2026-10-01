<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommsInterruptController
{
    public function __invoke(
        Request $request,
        CallSessionQueue $callSessionQueue,
        CommsInterruptResolver $resolver,
    ): JsonResponse {
        $callSessionQueue->reconcileStaleLiveSessions();

        if (WorkstationPresence::resolve($request)->operationalPrivacyActive()) {
            return response()
                ->json([
                    'call' => null,
                    'messages' => [],
                    'summary' => ['station_privacy_active' => true],
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return response()
            ->json($resolver->resolve($request->user()))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
