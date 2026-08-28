<?php

namespace App\Ark\Growth\Journey;

use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use App\Ark\Growth\Sessions\GrowthTouchpointType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

final readonly class JourneyComparisonProjection
{
    /**
     * @return array{
     *     average_path: list<string>,
     *     customer_path: list<string>,
     *     average_duration_days: int|null,
     *     customer_duration_days: int|null,
     *     narrative: string
     * }
     */
    public function forJourney(OperationalJourneyProjectionResult $journey): array
    {
        $customerPath = $journey->pathLabels;
        $customerDays = $journey->durationDays;

        $averagePath = $this->typicalPath();
        $averageDays = $this->typicalDurationDays();

        $narrative = $this->narrative($customerPath, $averagePath, $customerDays, $averageDays);

        return [
            'average_path' => $averagePath,
            'customer_path' => $customerPath,
            'average_duration_days' => $averageDays,
            'customer_duration_days' => $customerDays,
            'narrative' => $narrative,
        ];
    }

    /**
     * @return list<string>
     */
    private function typicalPath(): array
    {
        $attributedSessionIds = GrowthAttribution::query()
            ->where('revenue_cents', '>', 0)
            ->whereNotNull('growth_session_id')
            ->pluck('growth_session_id');

        if ($attributedSessionIds->isEmpty()) {
            return ['Google Search', 'Service Page', 'Call', 'Appointment', 'Repair'];
        }

        $paths = GrowthSession::query()
            ->whereIn('id', $attributedSessionIds)
            ->with('touchpoints')
            ->get()
            ->map(fn (GrowthSession $session): array => $this->compactPathForSession($session));

        $counts = $paths->countBy(fn (array $path): string => implode('>', $path));

        /** @var string $winner */
        $winner = $counts->sortDesc()->keys()->first() ?? '';

        if ($winner === '') {
            return ['Google Search', 'Service Page', 'Call', 'Appointment', 'Repair'];
        }

        return explode('>', $winner);
    }

    private function typicalDurationDays(): ?int
    {
        $rows = GrowthAttribution::query()
            ->where('revenue_cents', '>', 0)
            ->whereNotNull('growth_session_id')
            ->whereNotNull('repair_order_id')
            ->with(['repairOrder'])
            ->get();

        if ($rows->isEmpty()) {
            return 5;
        }

        $durations = $rows
            ->map(function (GrowthAttribution $row): ?int {
                $session = GrowthSession::query()->find($row->growth_session_id);
                $repairOrder = $row->repairOrder;

                if ($session?->started_at === null || $repairOrder === null) {
                    return null;
                }

                $end = $repairOrder->posted_at ?? $repairOrder->closed_at;

                if ($end === null) {
                    return null;
                }

                return max(0, (int) $session->started_at->startOfDay()->diffInDays($end->startOfDay()));
            })
            ->filter()
            ->values();

        if ($durations->isEmpty()) {
            return 5;
        }

        return (int) round($durations->avg());
    }

    /**
     * @return list<string>
     */
    private function compactPathForSession(GrowthSession $session): array
    {
        $labels = [];

        if (filled($session->first_search_query) || str_contains(strtolower((string) $session->first_referrer), 'google.')) {
            $labels[] = 'Google Search';
        } elseif (filled($session->utm_source)) {
            $labels[] = ucfirst((string) $session->utm_source);
        }

        if ($session->first_landing_page !== null) {
            $labels[] = 'Service Page';
        }

        $touchTypes = $session->touchpoints
            ->map(fn (GrowthTouchpoint $tp) => $tp->type)
            ->unique()
            ->values();

        if ($touchTypes->contains(GrowthTouchpointType::CallClicked)) {
            $labels[] = 'Call';
        }

        if ($touchTypes->contains(GrowthTouchpointType::AppointmentScheduled)) {
            $labels[] = 'Appointment';
        }

        $labels[] = 'Repair';

        return array_values(array_unique($labels));
    }

    /**
     * @param  list<string>  $customerPath
     * @param  list<string>  $averagePath
     */
    private function narrative(array $customerPath, array $averagePath, ?int $customerDays, ?int $averageDays): string
    {
        if ($customerPath === []) {
            return 'No attributed journey yet — comparison will appear once Growth session data is linked to this repair order.';
        }

        $durationNote = ($customerDays !== null && $averageDays !== null)
            ? sprintf('%d days versus shop average of %d days.', $customerDays, $averageDays)
            : 'Duration comparison will sharpen as more closed journeys accumulate.';

        $extraSteps = count($customerPath) - count($averagePath);

        if ($extraSteps > 1) {
            return sprintf(
                'This customer took a longer path (%d extra steps) before repair. %s',
                $extraSteps,
                $durationNote,
            );
        }

        if ($extraSteps < -1) {
            return sprintf('This customer converted faster than the typical path. %s', $durationNote);
        }

        return sprintf('This journey closely matches the shop\'s typical acquisition path. %s', $durationNote);
    }
}
