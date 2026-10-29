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
        $callSessionQueue->reconcileStaleLiveSessionsIfDue();

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

        $payload = $resolver->resolveAttention($request->user());
        $pressure = $navPressure->resolve($request->user());
        $summaryOnly = $request->boolean('summary');

        $body = [
            ...($summaryOnly ? $this->summaryPayload($payload) : $payload),
            'summary_only' => $summaryOnly,
            'nav_pressure_count' => $pressure['nav_pressure_count'],
            'workboard_counts' => $pressure['workboard_counts'],
        ];

        if (! $summaryOnly) {
            $body['html'] = view('operations.communications.partials.call-queue-items-list', [
                'items' => $payload['items'] ?? [],
            ])->render();
        }

        return response()
            ->json($body)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Closed-panel polling only needs the badge. The row list and its HTML
     * stay on the request that opens the panel.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summaryPayload(array $payload): array
    {
        return [
            'count' => $payload['count'] ?? 0,
            'summary' => $payload['summary'] ?? [],
            'queue_url' => $payload['queue_url'] ?? '',
        ];
    }
}
