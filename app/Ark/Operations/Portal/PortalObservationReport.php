<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PortalObservationReport
{
    private const TOP_VEHICLE_LIMIT = 10;
    /**
     * @return array{
     *     period: array{since: string, until: string},
     *     funnel: list<array{step: string, count: int, unique_sessions: ?int, rate_of_prior: ?string}>,
     *     event_volume: list<array{event: string, count: int, unique_sessions: int}>,
     *     vehicle_surface: list<array{signal: string, count: int, unique_sessions: int}>,
     *     historical_vs_active: list<array{context: string, vehicle_views: int, unique_sessions: int, share: string}>,
     *     vehicle_concentration: list<array{vehicle: string, vehicle_id: int, unique_sessions: int, vehicle_views: int}>,
     *     document_types: list<array{document_type: string, viewed: int, downloaded: int}>,
     * }
     */
    public function forPeriod(Carbon $since, Carbon $until): array
    {
        $events = OperationalEvent::query()
            ->whereIn('event_name', $this->portalEventNames())
            ->whereBetween('occurred_at', [$since, $until])
            ->orderBy('occurred_at')
            ->get();

        $challengesCreated = PortalAccessChallenge::query()
            ->whereBetween('created_at', [$since, $until])
            ->count();

        $challengesVerified = PortalAccessChallenge::query()
            ->whereBetween('used_at', [$since, $until])
            ->whereNotNull('used_at')
            ->count();

        $vehicleViews = $this->eventsNamed($events, OperationalEventName::PortalVehicleViewed);
        $documentViews = $this->eventsNamed($events, OperationalEventName::PortalDocumentViewed);
        $documentDownloads = $this->eventsNamed($events, OperationalEventName::PortalDocumentDownloaded);

        $funnel = [
            $this->funnelStep('Access challenge created', $challengesCreated, null, null),
            $this->funnelStep(
                'Access challenge verified',
                $challengesVerified,
                null,
                $this->rate($challengesVerified, $challengesCreated),
            ),
            $this->funnelStep(
                'Vehicle viewed',
                $vehicleViews->count(),
                $this->uniqueSessions($vehicleViews),
                $this->rate($vehicleViews->count(), $challengesVerified),
            ),
            $this->funnelStep(
                'Document viewed',
                $documentViews->count(),
                $this->uniqueSessions($documentViews),
                $this->rate($documentViews->count(), $vehicleViews->count()),
            ),
            $this->funnelStep(
                'Document downloaded',
                $documentDownloads->count(),
                $this->uniqueSessions($documentDownloads),
                $this->rate($documentDownloads->count(), $documentViews->count()),
            ),
        ];

        return [
            'period' => [
                'since' => $since->toDateTimeString(),
                'until' => $until->toDateTimeString(),
            ],
            'funnel' => $funnel,
            'event_volume' => $this->eventVolume($events),
            'vehicle_surface' => $this->vehicleSurface($events),
            'historical_vs_active' => $this->historicalVsActive($vehicleViews),
            'vehicle_concentration' => $this->vehicleConcentration($vehicleViews),
            'document_types' => $this->documentTypes($documentViews, $documentDownloads),
        ];
    }

    /**
     * @return list<string>
     */
    private function portalEventNames(): array
    {
        return [
            OperationalEventName::PortalVehicleViewed->value,
            OperationalEventName::PortalActiveVisitViewed->value,
            OperationalEventName::PortalCommunicationSectionViewed->value,
            OperationalEventName::PortalDocumentViewed->value,
            OperationalEventName::PortalDocumentDownloaded->value,
        ];
    }

    /**
     * @param  Collection<int, OperationalEvent>  $events
     * @return Collection<int, OperationalEvent>
     */
    private function eventsNamed(Collection $events, OperationalEventName $name): Collection
    {
        return $events->where('event_name', $name->value)->values();
    }

    /**
     * @param  Collection<int, OperationalEvent>  $events
     * @return list<array{event: string, count: int, unique_sessions: int}>
     */
    private function eventVolume(Collection $events): array
    {
        $ordered = [
            OperationalEventName::PortalVehicleViewed,
            OperationalEventName::PortalDocumentViewed,
            OperationalEventName::PortalDocumentDownloaded,
            OperationalEventName::PortalActiveVisitViewed,
        ];

        return collect($ordered)
            ->map(function (OperationalEventName $name) use ($events): array {
                $subset = $this->eventsNamed($events, $name);

                return [
                    'event' => $name->value,
                    'count' => $subset->count(),
                    'unique_sessions' => $this->uniqueSessions($subset),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalEvent>  $events
     * @return list<array{signal: string, count: int, unique_sessions: int}>
     */
    private function vehicleSurface(Collection $events): array
    {
        $signals = [
            'Documents viewed' => OperationalEventName::PortalDocumentViewed,
            'Documents downloaded' => OperationalEventName::PortalDocumentDownloaded,
            'Active visit present' => OperationalEventName::PortalActiveVisitViewed,
        ];

        return collect($signals)
            ->map(function (OperationalEventName $name, string $label) use ($events): array {
                $subset = $this->eventsNamed($events, $name);

                return [
                    'signal' => $label,
                    'count' => $subset->count(),
                    'unique_sessions' => $this->uniqueSessions($subset),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalEvent>  $vehicleViews
     * @return list<array{context: string, vehicle_views: int, unique_sessions: int, share: string}>
     */
    private function historicalVsActive(Collection $vehicleViews): array
    {
        $active = $vehicleViews->filter(
            fn (OperationalEvent $event): bool => ($event->payload_json['has_active_visit'] ?? false) === true,
        )->values();

        $historical = $vehicleViews->reject(
            fn (OperationalEvent $event): bool => ($event->payload_json['has_active_visit'] ?? false) === true,
        )->values();

        $total = max($vehicleViews->count(), 1);

        return [
            [
                'context' => 'Active visit vehicle',
                'vehicle_views' => $active->count(),
                'unique_sessions' => $this->uniqueSessions($active),
                'share' => $this->percent($active->count(), $total),
            ],
            [
                'context' => 'Historical vehicle',
                'vehicle_views' => $historical->count(),
                'unique_sessions' => $this->uniqueSessions($historical),
                'share' => $this->percent($historical->count(), $total),
            ],
        ];
    }

    /**
     * @param  Collection<int, OperationalEvent>  $vehicleViews
     * @return list<array{vehicle: string, vehicle_id: int, unique_sessions: int, vehicle_views: int}>
     */
    private function vehicleConcentration(Collection $vehicleViews): array
    {
        if ($vehicleViews->isEmpty()) {
            return [];
        }

        $grouped = $vehicleViews->groupBy(
            fn (OperationalEvent $event): int => $this->vehicleIdFromEvent($event),
        );

        $rows = $grouped
            ->map(function (Collection $events, int $vehicleId): array {
                return [
                    'vehicle_id' => $vehicleId,
                    'unique_sessions' => $this->uniqueSessions($events),
                    'vehicle_views' => $events->count(),
                ];
            })
            ->sortByDesc('unique_sessions')
            ->take(self::TOP_VEHICLE_LIMIT)
            ->values();

        $vehicles = Vehicle::query()
            ->whereIn('id', $rows->pluck('vehicle_id')->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function (array $row) use ($vehicles): array {
                $vehicle = $vehicles->get($row['vehicle_id']);

                return [
                    'vehicle' => $vehicle?->display_name ?? 'Vehicle #'.$row['vehicle_id'],
                    'vehicle_id' => $row['vehicle_id'],
                    'unique_sessions' => $row['unique_sessions'],
                    'vehicle_views' => $row['vehicle_views'],
                ];
            })
            ->all();
    }

    private function vehicleIdFromEvent(OperationalEvent $event): int
    {
        $vehicleId = $event->payload_json['vehicle_id'] ?? null;

        if (is_int($vehicleId)) {
            return $vehicleId;
        }

        if (is_string($vehicleId) && ctype_digit($vehicleId)) {
            return (int) $vehicleId;
        }

        if ($event->aggregate_type === Vehicle::class) {
            return (int) $event->aggregate_id;
        }

        return 0;
    }

    /**
     * @param  Collection<int, OperationalEvent>  $documentViews
     * @param  Collection<int, OperationalEvent>  $documentDownloads
     * @return list<array{document_type: string, viewed: int, downloaded: int}>
     */
    private function documentTypes(Collection $documentViews, Collection $documentDownloads): array
    {
        $types = $documentViews
            ->pluck('payload_json.document_type')
            ->merge($documentDownloads->pluck('payload_json.document_type'))
            ->filter(fn ($type): bool => is_string($type) && $type !== '')
            ->unique()
            ->sort()
            ->values();

        if ($types->isEmpty()) {
            return [];
        }

        return $types
            ->map(function (string $type) use ($documentViews, $documentDownloads): array {
                return [
                    'document_type' => $type,
                    'viewed' => $documentViews
                        ->where('payload_json.document_type', $type)
                        ->count(),
                    'downloaded' => $documentDownloads
                        ->where('payload_json.document_type', $type)
                        ->count(),
                ];
            })
            ->sortByDesc('viewed')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalEvent>  $events
     */
    private function uniqueSessions(Collection $events): int
    {
        return $events
            ->pluck('payload_json.portal_session_id')
            ->filter(fn ($sessionId): bool => is_string($sessionId) && $sessionId !== '')
            ->unique()
            ->count();
    }

    /**
     * @return array{step: string, count: int, unique_sessions: ?int, rate_of_prior: ?string}
     */
    private function funnelStep(string $step, int $count, ?int $uniqueSessions, ?string $rateOfPrior): array
    {
        return [
            'step' => $step,
            'count' => $count,
            'unique_sessions' => $uniqueSessions,
            'rate_of_prior' => $rateOfPrior,
        ];
    }

    private function rate(int $numerator, int $denominator): ?string
    {
        if ($denominator === 0) {
            return null;
        }

        return $this->percent($numerator, $denominator);
    }

    private function percent(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0%';
        }

        return number_format(($part / $whole) * 100, 1).'%';
    }
}
