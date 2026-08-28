<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Communications\CommunicationsNavPressure;
use App\Ark\Operations\Communications\CommunicationsQueueResolver;
use App\Ark\Operations\Workstations\WorkstationPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomingCallQueueController
{
    public function __invoke(
        Request $request,
        CallSessionQueue $callSessionQueue,
        CommunicationsQueueResolver $resolver,
        CommunicationsNavPressure $navPressure,
    ): JsonResponse {
        $callSessionQueue->reconcileStaleLiveSessions();

        if (WorkstationPresence::resolve($request)->operationalPrivacyActive()) {
            $payload = $resolver->privacyGatedAttention();

            return response()
                ->json([
                    ...$payload,
                    'nav_pressure_count' => 0,
                    'workboard_counts' => [],
                    'html' => view('operations.communications.partials.call-queue-items-list', [
                        'items' => [],
                    ])->render(),
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $payload = $resolver->resolve($request->user());
        $pressure = $navPressure->resolve($request->user());

        return response()
            ->json([
                ...$payload,
                'nav_pressure_count' => $pressure['nav_pressure_count'],
                'workboard_counts' => $pressure['workboard_counts'],
                'html' => view('operations.communications.partials.call-queue-items-list', [
                    'items' => $payload['items'] ?? [],
                ])->render(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
