<?php

namespace App\Ark\Growth\Projections;

use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use App\Ark\Growth\Sessions\GrowthTouchpointType;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class JourneyExplorerProjection
{
    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public function queryCatalog(): array
    {
        return [
            [
                'key' => 'high_revenue_repairs',
                'label' => 'High-revenue repairs',
                'description' => 'Journeys that ended in repairs above a revenue threshold.',
            ],
            [
                'key' => 'abandoned_after_estimate',
                'label' => 'Abandoned after estimate',
                'description' => 'Sessions with estimate views but no posted repair order.',
            ],
            [
                'key' => 'multi_page_before_call',
                'label' => 'Research before call',
                'description' => 'Customers who visited three or more pages before calling.',
            ],
            [
                'key' => 'estimate_views_before_approval',
                'label' => 'Estimate hesitation',
                'description' => 'Repair orders where estimate views exceeded five before approval.',
            ],
            [
                'key' => 'common_path_by_landing',
                'label' => 'Common path by landing page',
                'description' => 'Most frequent milestone sequence starting from a landing page keyword.',
            ],
            [
                'key' => 'first_touch_by_concern',
                'label' => 'First touch by concern',
                'description' => 'Most common first-touch page for repair orders matching a concern keyword.',
            ],
        ];
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    public function resolve(string $queryType, array $params = [], int $limit = 50): array
    {
        return match ($queryType) {
            'high_revenue_repairs' => $this->highRevenueRepairs(
                (int) ($params['threshold_cents'] ?? 250_000),
                $limit,
            ),
            'abandoned_after_estimate' => $this->abandonedAfterEstimate($limit),
            'multi_page_before_call' => $this->multiPageBeforeCall($limit),
            'estimate_views_before_approval' => $this->estimateViewsBeforeApproval(
                (int) ($params['min_views'] ?? 5),
                $limit,
            ),
            'common_path_by_landing' => $this->commonPathByLanding(
                (string) ($params['keyword'] ?? ''),
                $limit,
            ),
            'first_touch_by_concern' => $this->firstTouchByConcern(
                trim((string) ($params['keyword'] ?? '')) !== '' ? (string) $params['keyword'] : 'brake',
                $limit,
            ),
            default => [
                'question' => 'Select a journey query.',
                'rows' => [],
                'empty_hint' => null,
            ],
        };
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    private function highRevenueRepairs(int $thresholdCents, int $limit): array
    {
        $rows = GrowthAttribution::query()
            ->where('revenue_cents', '>=', $thresholdCents)
            ->whereNotNull('repair_order_id')
            ->with(['repairOrder.customer', 'repairOrder.vehicle'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthAttribution $row): array => [
                'repair_order' => '#'.($row->repairOrder?->repair_order_id ?? '—'),
                'revenue' => '$'.number_format($row->revenue_cents / 100, 0),
                'landing_page' => $row->landing_page ?? '—',
                'search_query' => $row->search_query ?? '—',
                'source' => $row->source ?? '—',
            ])
            ->all();

        return [
            'question' => sprintf('Show journeys that ended in repairs over $%s.', number_format($thresholdCents / 100, 0)),
            'rows' => $rows,
            'empty_hint' => $rows === [] ? 'No attributed high-revenue journeys yet. Closed ROs with Growth sessions will populate this view.' : null,
        ];
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    private function abandonedAfterEstimate(int $limit): array
    {
        $rows = GrowthSession::query()
            ->whereHas('touchpoints', fn (Builder $q) => $q->where('type', GrowthTouchpointType::EstimateSubmitted))
            ->whereDoesntHave('repairOrders', fn (Builder $q) => $q->whereNotNull('posted_at'))
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthSession $session): array => [
                'session' => (string) $session->id,
                'started' => $session->started_at?->format('M j, Y') ?? '—',
                'landing_page' => $session->first_landing_page ?? '—',
                'search_query' => $session->first_search_query ?? '—',
            ])
            ->all();

        return [
            'question' => 'Show journeys abandoned after an online estimate request.',
            'rows' => $rows,
            'empty_hint' => $rows === [] ? 'No abandoned estimate journeys detected yet.' : null,
        ];
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    private function multiPageBeforeCall(int $limit): array
    {
        $rows = GrowthSession::query()
            ->withCount([
                'touchpoints as page_views' => fn (Builder $q) => $q->where('type', GrowthTouchpointType::PageViewed),
            ])
            ->having('page_views', '>=', 3)
            ->whereHas('touchpoints', fn (Builder $q) => $q->where('type', GrowthTouchpointType::CallClicked))
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthSession $session): array => [
                'session' => (string) $session->id,
                'page_views' => (int) $session->page_views,
                'search_query' => $session->first_search_query ?? '—',
                'landing_page' => $session->first_landing_page ?? '—',
            ])
            ->all();

        return [
            'question' => 'Show customers who visited three or more pages before calling.',
            'rows' => $rows,
            'empty_hint' => $rows === [] ? 'Research-before-call patterns will appear as public touchpoints accumulate.' : null,
        ];
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    private function estimateViewsBeforeApproval(int $minViews, int $limit): array
    {
        $rows = RepairOrder::query()
            ->whereNotNull('growth_session_id')
            ->with(['customer'])
            ->latest('repair_order_id')
            ->limit(200)
            ->get()
            ->filter(function (RepairOrder $repairOrder) use ($minViews): bool {
                $views = CommunicationEvent::query()
                    ->where('repair_order_id', $repairOrder->repair_order_id)
                    ->where('event_type', OperationalCommunicationType::EstimateViewed)
                    ->count();

                return $views >= $minViews;
            })
            ->take($limit)
            ->map(fn (RepairOrder $repairOrder): array => [
                'repair_order' => '#'.$repairOrder->repair_order_id,
                'customer' => $repairOrder->customer?->display_name ?? '—',
                'estimate_views' => CommunicationEvent::query()
                    ->where('repair_order_id', $repairOrder->repair_order_id)
                    ->where('event_type', OperationalCommunicationType::EstimateViewed)
                    ->count(),
                'status' => $repairOrder->status->label(),
            ])
            ->values()
            ->all();

        return [
            'question' => sprintf('Show journeys where estimate views exceeded %d before approval.', $minViews),
            'rows' => $rows,
            'empty_hint' => $rows === [] ? 'No high-hesitation estimate journeys matched yet.' : null,
        ];
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    private function commonPathByLanding(string $keyword, int $limit): array
    {
        $sessions = GrowthSession::query()
            ->when($keyword !== '', fn (Builder $q) => $q->where('first_landing_page', 'like', '%'.$keyword.'%'))
            ->whereHas('repairOrders', fn (Builder $q) => $q->whereNotNull('posted_at'))
            ->with('touchpoints')
            ->latest('started_at')
            ->limit(100)
            ->get();

        $paths = $sessions
            ->map(fn (GrowthSession $session): string => $this->pathSignature($session))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take($limit);

        $rows = $paths->map(fn (int $count, string $path): array => [
            'path' => str_replace('>', ' → ', $path),
            'journeys' => $count,
        ])->values()->all();

        return [
            'question' => $keyword !== ''
                ? sprintf('Most common path to repair starting from landing pages matching "%s".', $keyword)
                : 'Most common path to repair by landing page.',
            'rows' => $rows,
            'empty_hint' => $rows === [] ? 'Path clustering needs more closed journeys with Growth sessions.' : null,
        ];
    }

    /**
     * @return array{question: string, rows: list<array<string, string|int>>, empty_hint: string|null}
     */
    private function firstTouchByConcern(string $keyword, int $limit): array
    {
        $rows = RepairOrder::query()
            ->whereNotNull('growth_session_id')
            ->where(function (Builder $query) use ($keyword): void {
                $query->where('concern_summary', 'like', '%'.$keyword.'%')
                    ->orWhereHas('concerns', fn (Builder $q) => $q->where('summary', 'like', '%'.$keyword.'%'));
            })
            ->with('growthSession')
            ->latest('repair_order_id')
            ->limit(200)
            ->get()
            ->groupBy(fn (RepairOrder $ro) => $ro->growthSession?->first_landing_page ?? 'unknown')
            ->map(fn (Collection $group, string $landing): array => [
                'first_touch_page' => $landing,
                'repair_orders' => $group->count(),
            ])
            ->sortByDesc('repair_orders')
            ->take($limit)
            ->values()
            ->all();

        return [
            'question' => sprintf('Most common first-touch page for repair orders related to "%s".', $keyword),
            'rows' => $rows,
            'empty_hint' => $rows === [] ? 'Concern-based first-touch analysis needs repair orders linked to Growth sessions.' : null,
        ];
    }

    private function pathSignature(GrowthSession $session): string
    {
        $parts = [];

        if ($session->first_landing_page !== null) {
            $parts[] = basename($session->first_landing_page);
        }

        foreach ($session->touchpoints->sortBy('recorded_at') as $touchpoint) {
            $label = match ($touchpoint->type) {
                GrowthTouchpointType::CallClicked => 'call',
                GrowthTouchpointType::AppointmentScheduled => 'appointment',
                GrowthTouchpointType::CalculatorUsed => 'calculator',
                GrowthTouchpointType::PageViewed => $touchpoint->path !== null ? basename($touchpoint->path) : 'page',
                default => null,
            };

            if ($label !== null) {
                $parts[] = $label;
            }
        }

        $parts[] = 'repair';

        return implode('>', array_values(array_unique($parts)));
    }
}
