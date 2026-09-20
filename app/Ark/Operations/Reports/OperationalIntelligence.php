<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class OperationalIntelligence
{
    private readonly int $opportunityRateCents;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {
        $settings = ShopSettings::current();
        $defaultCategory = $settings->defaultLaborCategory();
        $this->opportunityRateCents = (int) ($defaultCategory['rate_cents'] ?? $settings->default_labor_rate_cents);
    }

    /**
     * @return array{
     *     buckets: list<array{label: string, current: int, prior_period: int}>,
     *     drilldown: list<array{repair_order_id: int, customer: string, vehicle: string, bucket: string, status: string, age: string, last_activity: string, url: string}>
     * }
     */
    public function operationalTruth(): array
    {
        $active = $this->activeQueueRepairOrders();

        $waitingApproval = $active->where('status', RepairOrderStatus::WaitingApproval);
        $waitingParts = $active->filter(fn (RepairOrder $repairOrder): bool => $this->isWaitingParts($repairOrder));
        $readyPickup = $active->where('status', RepairOrderStatus::ReadyPickup);
        $waitingCustomer = $waitingApproval->filter(fn (RepairOrder $repairOrder): bool => $this->isWaitingCustomer($repairOrder));

        $bucketDefinitions = [
            ['label' => 'Waiting Approval', 'collection' => $waitingApproval],
            ['label' => 'Waiting Parts', 'collection' => $waitingParts],
            ['label' => 'Ready Pickup', 'collection' => $readyPickup],
            ['label' => 'Waiting Customer', 'collection' => $waitingCustomer],
        ];

        $buckets = [];
        $drilldown = [];

        foreach ($bucketDefinitions as $definition) {
            /** @var Collection<int, RepairOrder> $collection */
            $collection = $definition['collection'];
            $buckets[] = [
                'label' => $definition['label'],
                'current' => $collection->count(),
                'prior_period' => $collection->filter(
                    fn (RepairOrder $repairOrder): bool => $repairOrder->updated_at->lte($this->from),
                )->count(),
            ];

            foreach ($collection as $repairOrder) {
                $drilldown[] = $this->truthDrilldownRow($repairOrder, $definition['label']);
            }
        }

        usort($drilldown, fn (array $left, array $right): int => [$left['bucket'], $left['age_minutes']] <=> [$right['bucket'], $right['age_minutes']]);

        return [
            'buckets' => $buckets,
            'drilldown' => $drilldown,
        ];
    }

    /**
     * @return array{
     *     stages: list<array{label: string, count: int}>,
     *     drilldown: list<array{repair_order_id: int, customer: string, sent: string, viewed: string, last_activity: string, age: string, url: string}>
     * }
     */
    public function approvalMomentum(): array
    {
        $openedInRangeIds = OperationalReportDateScope::openedBetween(RepairOrder::query(), $this->from, $this->to)
            ->pluck('id');

        $communications = CommunicationEvent::query()
            ->whereIn('repair_order_id', $openedInRangeIds)
            ->whereIn('event_type', [
                OperationalCommunicationType::EstimateSent,
                OperationalCommunicationType::EstimateViewed,
            ])
            ->get()
            ->groupBy('repair_order_id');

        $sentCount = $communications
            ->filter(fn (Collection $events): bool => $events->contains(
                fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateSent
                    && $event->occurred_at->between($this->from, $this->to),
            ))
            ->count();

        $viewedCount = $communications
            ->filter(fn (Collection $events): bool => $events->contains(
                fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateViewed
                    && $event->occurred_at->between($this->from, $this->to),
            ))
            ->count();

        $approvedCount = ApprovalEvent::query()
            ->whereIn('visit_id', $openedInRangeIds)
            ->whereBetween('approved_at', [$this->from, $this->to])
            ->distinct()
            ->count('visit_id');

        $awaitingRepairOrders = $this->activeQueueRepairOrders()
            ->where('status', RepairOrderStatus::WaitingApproval)
            ->filter(fn (RepairOrder $repairOrder): bool => $this->isWaitingCustomer($repairOrder));

        $agingThreshold = now()->subDays(3);
        $agingRepairOrders = $awaitingRepairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $this->approvalLastActivityAt($repairOrder)?->lt($agingThreshold) ?? $repairOrder->updated_at->lt($agingThreshold),
        );

        $drilldownRepairOrders = RepairOrder::query()
            ->with([
                'customer:id,first_name,last_name',
            ])
            ->whereIn('id', $communications->keys()->merge($awaitingRepairOrders->pluck('id'))->unique())
            ->get()
            ->sortByDesc(fn (RepairOrder $repairOrder): Carbon => $this->approvalLastActivityAt($repairOrder) ?? $repairOrder->updated_at);

        $drilldown = $drilldownRepairOrders
            ->map(function (RepairOrder $repairOrder) use ($communications): array {
                $events = $communications->get($repairOrder->id, collect());
                $sentAt = $events
                    ->filter(fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateSent)
                    ->sortByDesc('occurred_at')
                    ->first()
                    ?->occurred_at;
                $viewedAt = $events
                    ->filter(fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateViewed)
                    ->sortByDesc('occurred_at')
                    ->first()
                    ?->occurred_at;
                $lastActivity = $this->approvalLastActivityAt($repairOrder) ?? $repairOrder->updated_at;

                return [
                    'repair_order_id' => $repairOrder->repair_order_id,
                    'customer' => $repairOrder->customer->name,
                    'sent' => $this->formatTimestamp($sentAt),
                    'viewed' => $this->formatTimestamp($viewedAt),
                    'last_activity' => $this->formatTimestamp($lastActivity),
                    'age' => $this->formatAge($lastActivity),
                    'url' => route('operations.repair-orders.show', $repairOrder->repair_order_id),
                ];
            })
            ->values()
            ->all();

        return [
            'stages' => [
                ['label' => 'Sent', 'count' => $sentCount],
                ['label' => 'Viewed', 'count' => $viewedCount],
                ['label' => 'Approved', 'count' => $approvedCount],
                ['label' => 'Awaiting Response', 'count' => $awaitingRepairOrders->count()],
                ['label' => 'Aging > 3 Days', 'count' => $agingRepairOrders->count()],
            ],
            'drilldown' => $drilldown,
        ];
    }

    /**
     * @return array{
     *     rows: list<array{category: string, hours: string, value: string}>,
     *     total_hours: string,
     *     total_value: string,
     * }
     */
    public function liability(): array
    {
        $lines = $this->liabilityLaborLines();

        $totals = [
            'courtesy' => ['hours' => 0.0, 'value_cents' => 0],
            'comeback' => ['hours' => 0.0, 'value_cents' => 0],
            'internal' => ['hours' => 0.0, 'value_cents' => 0],
        ];

        foreach ($lines as $line) {
            $category = $this->liabilityCategoryForLine($line);
            if ($category === null) {
                continue;
            }

            $hours = $this->lineLaborHours($line);
            $rateCents = $this->opportunityRateForLine($line);
            $totals[$category]['hours'] += $hours;
            $totals[$category]['value_cents'] += (int) round($hours * $rateCents);
        }

        $rows = [
            ['category' => 'Courtesy', ...$this->liabilityRow($totals['courtesy'])],
            ['category' => 'Comeback', ...$this->liabilityRow($totals['comeback'])],
            ['category' => 'Internal', ...$this->liabilityRow($totals['internal'])],
        ];

        $totalHours = $totals['courtesy']['hours'] + $totals['comeback']['hours'] + $totals['internal']['hours'];
        $totalValueCents = $totals['courtesy']['value_cents'] + $totals['comeback']['value_cents'] + $totals['internal']['value_cents'];

        return [
            'rows' => $rows,
            'total_hours' => $this->formatHours($totalHours),
            'total_value' => $this->money($totalValueCents),
        ];
    }

    /**
     * @return list<array{
     *     intent: string,
     *     recommended: int,
     *     approved: int,
     *     deferred: int,
     *     deferred_value: string,
     * }>
     */
    /**
     * @return array{diagnostic_ros: int, repair_follow_through: int, rate_label: string, hint: string}
     */
    public function diagnosticRepairFollowThrough(): array
    {
        $repairOrders = OperationalReportDateScope::openedBetween(
            RepairOrder::query()->with('concerns'),
            $this->from,
            $this->to,
        )->get();

        $withDiagnostic = $repairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->concerns->contains(
                fn (RepairOrderConcern $concern): bool => $concern->recommendationIntent() === RecommendationIntent::Diagnostic,
            ),
        );

        $followedThrough = $withDiagnostic->filter(function (RepairOrder $repairOrder): bool {
            return $repairOrder->concerns->contains(function (RepairOrderConcern $concern): bool {
                return $concern->recommendationIntent() !== RecommendationIntent::Diagnostic
                    && $concern->disposition === RepairOrderConcernDisposition::Approved;
            });
        });

        $total = $withDiagnostic->count();
        $converted = $followedThrough->count();

        return [
            'diagnostic_ros' => $total,
            'repair_follow_through' => $converted,
            'rate_label' => $total > 0 ? (string) round(($converted / $total) * 100).'%' : '—',
            'hint' => 'ROs opened in range with a diagnostic scope that also gained approved repair work',
        ];
    }

    public function recommendationConversion(): array
    {
        $concerns = RepairOrderConcern::query()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_concerns.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applyOpenedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->select('repair_order_concerns.*')
            ->with(['lines:id,repair_order_concern_id,subtotal_cents'])
            ->get();

        return collect(RecommendationIntent::cases())
            ->map(function (RecommendationIntent $intent) use ($concerns): array {
                $intentConcerns = $concerns->filter(
                    fn (RepairOrderConcern $concern): bool => $concern->recommendationIntent() === $intent,
                );

                $deferredConcerns = $intentConcerns->where('disposition', RepairOrderConcernDisposition::Deferred);

                return [
                    'intent' => $intent->staffLabel(),
                    'recommended' => $intentConcerns->where('disposition', RepairOrderConcernDisposition::Recommended)->count(),
                    'approved' => $intentConcerns->where('disposition', RepairOrderConcernDisposition::Approved)->count(),
                    'deferred' => $deferredConcerns->count(),
                    'deferred_value' => $this->money((int) $deferredConcerns->sum(
                        fn (RepairOrderConcern $concern): int => (int) $concern->lines->sum('subtotal_cents'),
                    )),
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function activeQueueRepairOrders(): Collection
    {
        return RepairOrder::query()
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
                'communicationEvents' => fn ($query) => $query->latest('occurred_at')->latest('id'),
                'approvalEvents:id,visit_id,approved_at',
            ])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applyTrustworthyDataFloor($query))
            ->get();
    }

    private function isWaitingParts(RepairOrder $repairOrder): bool
    {
        return $repairOrder->workboardLaneStatus() === RepairOrderStatus::WaitingParts;
    }

    private function isWaitingCustomer(RepairOrder $repairOrder): bool
    {
        if (! $repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return false;
        }

        if ($repairOrder->approvalEvents->isNotEmpty()) {
            return false;
        }

        return $repairOrder->communicationEvents->contains(
            fn (CommunicationEvent $event): bool => in_array($event->event_type, [
                OperationalCommunicationType::EstimateSent,
                OperationalCommunicationType::EstimateViewed,
                OperationalCommunicationType::ApprovalFollowUp,
            ], true),
        );
    }

    /**
     * @return array{repair_order_id: int, customer: string, vehicle: string, bucket: string, status: string, age: string, age_minutes: int, last_activity: string, url: string}
     */
    private function truthDrilldownRow(RepairOrder $repairOrder, string $bucket): array
    {
        $lastActivity = $this->approvalLastActivityAt($repairOrder) ?? $repairOrder->updated_at;

        return [
            'repair_order_id' => $repairOrder->repair_order_id,
            'customer' => $repairOrder->customer->name,
            'vehicle' => $repairOrder->vehicle->display_name,
            'bucket' => $bucket,
            'status' => $repairOrder->status->label(),
            'age' => $this->formatAge($lastActivity),
            'age_minutes' => max(0, (int) $lastActivity->diffInMinutes(now())),
            'last_activity' => $this->formatTimestamp($lastActivity),
            'url' => route('operations.repair-orders.show', $repairOrder->repair_order_id),
        ];
    }

    private function approvalLastActivityAt(RepairOrder $repairOrder): ?Carbon
    {
        $communicationAt = $repairOrder->communicationEvents->first()?->occurred_at;
        $approvalAt = $repairOrder->approvalEvents->first()?->approved_at;

        return collect([$communicationAt, $approvalAt, $repairOrder->updated_at])
            ->filter()
            ->sortDesc()
            ->first();
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function liabilityLaborLines(): Collection
    {
        return RepairOrderLine::query()
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->join('customers', 'customers.id', '=', 'repair_orders.customer_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applyOpenedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Approved)
            ->select([
                'repair_order_lines.*',
                'repair_order_concerns.billing_posture',
                'customers.customer_type',
            ])
            ->get();
    }

    private function liabilityCategoryForLine(RepairOrderLine $line): ?string
    {
        $postureValue = (string) ($line->billing_posture ?? '');
        $customerType = (string) ($line->customer_type ?? '');
        $laborCategoryKey = (string) ($line->labor_category_key ?? '');

        if ($postureValue === 'comeback' || $customerType === 'Comeback') {
            return 'comeback';
        }

        if ($postureValue === 'internal' || $customerType === 'Internal') {
            return 'internal';
        }

        if ($laborCategoryKey === 'courtesy') {
            return 'courtesy';
        }

        if ($laborCategoryKey === 'comeback') {
            return 'comeback';
        }

        return null;
    }

    private function lineLaborHours(RepairOrderLine $line): float
    {
        if (Schema::hasColumn('repair_order_lines', 'labor_billed_hours') && $line->labor_billed_hours !== null) {
            return (float) $line->labor_billed_hours;
        }

        return (float) $line->quantity;
    }

    private function opportunityRateForLine(RepairOrderLine $line): int
    {
        $lineRate = max((int) ($line->labor_rate_cents ?? 0), (int) $line->unit_price_cents);

        return $lineRate > 0 ? $lineRate : $this->opportunityRateCents;
    }

    /**
     * @param  array{hours: float, value_cents: int}  $totals
     * @return array{hours: string, value: string}
     */
    private function liabilityRow(array $totals): array
    {
        return [
            'hours' => $this->formatHours($totals['hours']),
            'value' => $this->money($totals['value_cents']),
        ];
    }

    private function formatHours(float $hours): string
    {
        return number_format($hours, 1);
    }

    private function formatTimestamp(?Carbon $timestamp): string
    {
        return $timestamp?->timezone(OperationalReportDateScope::displayTimezone())->format('M j, g:i A') ?? '—';
    }

    private function formatAge(Carbon $timestamp): string
    {
        return str_replace(' ago', '', $timestamp->diffForHumans(short: true, parts: 1));
    }

    private function money(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }
}
