<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use Illuminate\Support\Carbon;

/**
 * Read-only Common Problems form observation — exposures through outcomes, grouped by variant.
 *
 * Notebook columns: Variant · Exposures · Starts · Submits · Leads · Scheduled · Arrived · Revenue
 *
 * Funnel decomposition (watch these before changing copy):
 * - start_rate = starts ÷ exposures  (copy/placement vs form)
 * - submit_from_start_rate = submits ÷ starts  (form friction)
 * - scheduled_rate = scheduled ÷ submits  (advisor workflow after submit)
 */
class CommonProblemFormCopySummary
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array{
     *     period_from: string,
     *     period_to: string,
     *     variants: list<array{
     *         variant: string,
     *         exposures: int,
     *         starts: int,
     *         submits: int,
     *         leads: int,
     *         scheduled: int,
     *         arrived: int,
     *         converted: int,
     *         revenue_cents: int,
     *         start_rate: float|null,
     *         submit_from_start_rate: float|null,
     *         submit_rate: float|null,
     *         scheduled_rate: float|null,
     *     }>
     * }
     */
    public function forPeriod(Carbon $from, Carbon $to): array
    {
        $variantKeys = [...array_keys(CommonProblemFormCopy::variants()), 'contextual'];

        $events = PublicSurfaceEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('context')
            ->get(['event', 'context']);

        $exposures = [];
        $starts = [];
        $submits = [];

        foreach ($events as $event) {
            $variant = (string) data_get($event->context, 'variant', '');
            $page = (string) data_get($event->context, 'page', '');

            if ($variant === '' || ! str_starts_with($page, 'common-problems')) {
                continue;
            }

            match ($event->event) {
                PublicSurfaceEventType::FormExposed->value => $exposures[$variant] = ($exposures[$variant] ?? 0) + 1,
                PublicSurfaceEventType::LeadStarted->value => $starts[$variant] = ($starts[$variant] ?? 0) + 1,
                PublicSurfaceEventType::LeadSubmitted->value => $submits[$variant] = ($submits[$variant] ?? 0) + 1,
                default => null,
            };
        }

        $leadOutcomes = [];

        Lead::query()
            ->notSpam()
            ->where('source', LeadSource::Website)
            ->whereBetween('created_at', [$from, $to])
            ->with('repairOrder')
            ->get(['id', 'metadata', 'scheduled_at', 'arrived_at', 'converted_at', 'repair_order_id'])
            ->each(function (Lead $lead) use (&$leadOutcomes): void {
                $page = (string) data_get($lead->metadata, 'public_surface.page', '');
                $variant = (string) data_get($lead->metadata, 'public_surface.variant', '');

                if ($variant === '' || ! str_starts_with($page, 'common-problems')) {
                    return;
                }

                $leadOutcomes[$variant] ??= [
                    'leads' => 0,
                    'scheduled' => 0,
                    'arrived' => 0,
                    'converted' => 0,
                    'revenue_cents' => 0,
                ];

                $leadOutcomes[$variant]['leads']++;
                if ($lead->scheduled_at !== null) {
                    $leadOutcomes[$variant]['scheduled']++;
                }
                if ($lead->arrived_at !== null) {
                    $leadOutcomes[$variant]['arrived']++;
                }
                if ($lead->converted_at !== null) {
                    $leadOutcomes[$variant]['converted']++;
                }

                $repairOrder = $lead->repairOrder;
                if ($repairOrder === null || $repairOrder->posted_at === null) {
                    return;
                }

                if ($repairOrder->close_variant_key === 'lost') {
                    return;
                }

                $leadOutcomes[$variant]['revenue_cents'] += $this->totalsCalculator
                    ->totalsFor($repairOrder)
                    ->totalCents();
            });

        $variants = [];

        foreach ($variantKeys as $variantKey) {
            $exposureCount = (int) ($exposures[$variantKey] ?? 0);
            $startCount = (int) ($starts[$variantKey] ?? 0);
            $submitCount = (int) ($submits[$variantKey] ?? 0);
            $outcome = $leadOutcomes[$variantKey] ?? [
                'leads' => 0,
                'scheduled' => 0,
                'arrived' => 0,
                'converted' => 0,
                'revenue_cents' => 0,
            ];
            $scheduled = (int) $outcome['scheduled'];

            $variants[] = [
                'variant' => $variantKey,
                'exposures' => $exposureCount,
                'starts' => $startCount,
                'submits' => $submitCount,
                'leads' => (int) $outcome['leads'],
                'scheduled' => $scheduled,
                'arrived' => (int) $outcome['arrived'],
                'converted' => (int) $outcome['converted'],
                'revenue_cents' => (int) $outcome['revenue_cents'],
                'start_rate' => $this->stepRate($exposureCount, $startCount),
                'submit_from_start_rate' => $this->stepRate($startCount, $submitCount),
                'submit_rate' => $this->stepRate($exposureCount, $submitCount),
                'scheduled_rate' => $this->stepRate($submitCount, $scheduled),
            ];
        }

        return [
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'variants' => $variants,
        ];
    }

    private function stepRate(int $from, int $to): ?float
    {
        if ($from <= 0) {
            return null;
        }

        return round(($to / $from) * 100, 1);
    }
}
