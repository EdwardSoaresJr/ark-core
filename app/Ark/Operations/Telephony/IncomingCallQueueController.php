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
        CommunicationsQueueResolver $resolver,
        CommunicationsNavPressure $navPressure,
    ): JsonResponse {
        if (WorkstationPresence::resolve($request)->operationalPrivacyActive()) {
            return $this->jsonPayload($request, [
                ...$resolver->privacyGatedAttention(),
                'nav_pressure_count' => 0,
                'workboard_counts' => [],
            ], []);
        }

        $payload = $resolver->resolveAttention($request->user());
        $pressure = $navPressure->resolve($request->user());

        return $this->jsonPayload($request, [
            ...$payload,
            'nav_pressure_count' => $pressure['nav_pressure_count'],
            'workboard_counts' => $pressure['workboard_counts'],
        ], $payload['items'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $items
     */
    private function jsonPayload(Request $request, array $payload, array $items): JsonResponse
    {
        if ($request->boolean('include_html')) {
            $payload['html'] = view('operations.communications.partials.call-queue-items-list', [
                'items' => $items,
            ])->render();
        }

        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
