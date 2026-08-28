<?php

namespace App\Ark\Operations\Work;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AdvisorWorkProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array{
     *     overdue: list<array<string, mixed>>,
     *     today: list<array<string, mixed>>,
     *     tomorrow: list<array<string, mixed>>,
     *     upcoming: list<array<string, mixed>>,
     *     total_count: int,
     *     overdue_count: int,
     *     overdue_opportunity_cents: int,
     *     overdue_opportunity_label: ?string,
     * }
     */
    public function followUpsForShop(?User $viewer = null): array
    {
        $items = $this->sortItemsForViewer(
            $this->openFollowUpQuery()->get(),
            $viewer?->id,
        );

        $grouped = $this->groupItems($items, 'follow_up', $viewer?->id);
        $overdueItems = $items->filter(fn (AdvisorFollowUp $item): bool => $this->bucket($item->due_at) === 'overdue');
        $opportunity = $this->overdueOpportunitySummary($overdueItems);

        return [
            ...$grouped,
            'overdue_count' => count($grouped['overdue']),
            ...$opportunity,
        ];
    }

    /**
     * @return array{
     *     overdue: list<array<string, mixed>>,
     *     today: list<array<string, mixed>>,
     *     tomorrow: list<array<string, mixed>>,
     *     upcoming: list<array<string, mixed>>,
     *     total_count: int,
     * }
     */
    public function tasksForShop(?User $viewer = null): array
    {
        $items = $this->sortItemsForViewer(
            $this->openTaskQuery()->get(),
            $viewer?->id,
        );

        return $this->groupItems($items, 'task', $viewer?->id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openFollowUpsForCustomer(Customer $customer, ?User $viewer = null): array
    {
        return $this->sortItemsForViewer(
            $this->openFollowUpQuery()
                ->where(function ($query) use ($customer): void {
                    $query->where('customer_id', $customer->id)
                        ->orWhereHas('repairOrder', fn ($repairOrders) => $repairOrders->where('customer_id', $customer->id));
                })
                ->get(),
            $viewer?->id,
        )
            ->map(fn (AdvisorFollowUp $item): array => $this->presentItem($item, 'follow_up', $viewer?->id))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openTasksForCustomer(Customer $customer, ?User $viewer = null): array
    {
        return $this->sortItemsForViewer(
            $this->openTaskQuery()
                ->where(function ($query) use ($customer): void {
                    $query->where('customer_id', $customer->id)
                        ->orWhereHas('repairOrder', fn ($repairOrders) => $repairOrders->where('customer_id', $customer->id));
                })
                ->get(),
            $viewer?->id,
        )
            ->map(fn (AdvisorTask $item): array => $this->presentItem($item, 'task', $viewer?->id))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openFollowUpsForRepairOrder(RepairOrder $repairOrder, ?User $viewer = null): array
    {
        return $this->sortItemsForViewer(
            $this->openFollowUpQuery()
                ->where('repair_order_id', $repairOrder->id)
                ->get(),
            $viewer?->id,
        )
            ->map(fn (AdvisorFollowUp $item): array => $this->presentItem($item, 'follow_up', $viewer?->id))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openTasksForRepairOrder(RepairOrder $repairOrder, ?User $viewer = null): array
    {
        return $this->sortItemsForViewer(
            $this->openTaskQuery()
                ->where('repair_order_id', $repairOrder->id)
                ->get(),
            $viewer?->id,
        )
            ->map(fn (AdvisorTask $item): array => $this->presentItem($item, 'task', $viewer?->id))
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AdvisorFollowUp>
     */
    private function openFollowUpQuery()
    {
        return AdvisorFollowUp::query()
            ->whereNull('completed_at')
            ->with(['creator', 'customer', 'repairOrder.customer', 'repairOrder.vehicle', 'repairOrder.lines.concern', 'vehicle'])
            ->orderBy('due_at');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AdvisorTask>
     */
    private function openTaskQuery()
    {
        return AdvisorTask::query()
            ->whereNull('completed_at')
            ->with(['creator', 'customer', 'repairOrder.customer', 'repairOrder.vehicle', 'vehicle'])
            ->orderBy('due_at');
    }

    /**
     * @param  Collection<int, AdvisorFollowUp|AdvisorTask>  $items
     * @return array{
     *     overdue: list<array<string, mixed>>,
     *     today: list<array<string, mixed>>,
     *     tomorrow: list<array<string, mixed>>,
     *     upcoming: list<array<string, mixed>>,
     *     total_count: int,
     * }
     */
    private function groupItems(Collection $items, string $kind, ?int $viewerUserId = null): array
    {
        $overdue = [];
        $today = [];
        $tomorrow = [];
        $upcoming = [];

        foreach ($items as $item) {
            $row = $this->presentItem($item, $kind, $viewerUserId);

            match ($this->bucket($item->due_at)) {
                'overdue' => $overdue[] = $row,
                'today' => $today[] = $row,
                'tomorrow' => $tomorrow[] = $row,
                default => $upcoming[] = $row,
            };
        }

        return [
            'overdue' => $overdue,
            'today' => $today,
            'tomorrow' => $tomorrow,
            'upcoming' => $upcoming,
            'total_count' => count($overdue) + count($today) + count($tomorrow) + count($upcoming),
        ];
    }

    /**
     * @param  Collection<int, AdvisorFollowUp|AdvisorTask>  $items
     * @return Collection<int, AdvisorFollowUp|AdvisorTask>
     */
    private function sortItemsForViewer(Collection $items, ?int $viewerUserId): Collection
    {
        if ($viewerUserId === null) {
            return $items->sortBy(fn (Model $item) => $item->due_at?->timestamp ?? PHP_INT_MAX)->values();
        }

        return $items->sortBy([
            fn (Model $item): int => (int) $item->created_by_user_id === $viewerUserId ? 0 : 1,
            fn (Model $item) => $item->due_at?->timestamp ?? PHP_INT_MAX,
        ])->values();
    }

    /**
     * @param  Collection<int, AdvisorFollowUp>  $overdueItems
     * @return array{overdue_opportunity_cents: int, overdue_opportunity_label: ?string}
     */
    private function overdueOpportunitySummary(Collection $overdueItems): array
    {
        $centsByRepairOrder = [];

        foreach ($overdueItems as $item) {
            if ($item->repair_order_id === null || isset($centsByRepairOrder[$item->repair_order_id])) {
                continue;
            }

            $repairOrder = $item->repairOrder;

            if ($repairOrder === null) {
                continue;
            }

            $centsByRepairOrder[$item->repair_order_id] = $this->opportunityCentsFor($repairOrder);
        }

        $totalCents = array_sum($centsByRepairOrder);

        return [
            'overdue_opportunity_cents' => $totalCents,
            'overdue_opportunity_label' => $totalCents > 0
                ? '$'.number_format($totalCents / 100, 0)
                : null,
        ];
    }

    private function opportunityCentsFor(RepairOrder $repairOrder): int
    {
        $approvedCents = $this->totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();

        if ($approvedCents > 0) {
            return $approvedCents;
        }

        return $this->totalsCalculator->totalsFor($repairOrder)->totalCents();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(Model $item, string $kind, ?int $viewerUserId = null): array
    {
        /** @var AdvisorFollowUp|AdvisorTask $item */
        $customer = $item->customer;
        $repairOrder = $item->repairOrder;

        if ($customer === null && $repairOrder?->customer !== null) {
            $customer = $repairOrder->customer;
        }

        $customerName = $customer !== null
            ? trim($customer->first_name.' '.$customer->last_name)
            : null;

        $context = $this->contextLabel($item, $customer, $repairOrder);
        $dollarsAtRiskCents = $kind === 'follow_up' && $repairOrder !== null
            ? $this->opportunityCentsFor($repairOrder)
            : 0;
        $assignedToLabel = $item->creator?->name ?? 'Shop';
        $isMine = $viewerUserId !== null && (int) $item->created_by_user_id === $viewerUserId;
        $scheduleBucket = $this->bucket($item->due_at);
        $displayTimezone = config('app.display_timezone');

        return [
            'id' => $item->id,
            'kind' => $kind,
            'notes' => $item->notes,
            'customer_name' => $customerName,
            'assigned_to_label' => $assignedToLabel,
            'is_mine' => $isMine,
            'created_by_user_id' => $item->created_by_user_id,
            'dollars_at_risk_label' => $dollarsAtRiskCents > 0
                ? '$'.number_format($dollarsAtRiskCents / 100, 0)
                : null,
            'schedule_bucket' => $scheduleBucket,
            'schedule_label' => $this->scheduleLabel($item->due_at, $scheduleBucket),
            'due_time_label' => $item->due_at?->timezone($displayTimezone)->format('g:i A') ?? '',
            'due_at_label' => $item->due_at?->timezone($displayTimezone)->format('M j, g:i A') ?? '',
            'due_relative_label' => $this->dueRelativeLabel($item->due_at, $scheduleBucket),
            'context_label' => $context,
            'customer_url' => $customer !== null ? route('operations.customers.show', $customer) : null,
            'repair_order_url' => $repairOrder !== null ? route('operations.repair-orders.show', $repairOrder) : null,
            'complete_url' => $kind === 'follow_up'
                ? route('operations.work.follow-ups.complete', $item)
                : route('operations.work.tasks.complete', $item),
        ];
    }

    private function contextLabel(Model $item, ?Customer $customer, ?RepairOrder $repairOrder): string
    {
        if ($repairOrder !== null) {
            return 'RO #'.$repairOrder->repair_order_id;
        }

        if ($customer !== null) {
            return trim($customer->first_name.' '.$customer->last_name);
        }

        if ($item->vehicle !== null) {
            return trim(collect([
                $item->vehicle->year,
                $item->vehicle->make,
                $item->vehicle->model,
            ])->filter()->implode(' '));
        }

        return 'Standalone';
    }

    private function bucket(?Carbon $dueAt): string
    {
        if (! $dueAt instanceof Carbon) {
            return 'upcoming';
        }

        $dueDay = $dueAt->copy()->timezone(config('app.display_timezone'))->startOfDay();
        $today = now()->timezone(config('app.display_timezone'))->startOfDay();
        $tomorrow = $today->copy()->addDay();

        if ($dueDay->lt($today)) {
            return 'overdue';
        }

        if ($dueDay->equalTo($today)) {
            return 'today';
        }

        if ($dueDay->equalTo($tomorrow)) {
            return 'tomorrow';
        }

        return 'upcoming';
    }

    private function scheduleLabel(?Carbon $dueAt, string $bucket): string
    {
        if (! $dueAt instanceof Carbon) {
            return '';
        }

        if ($bucket === 'overdue') {
            return $this->dueRelativeLabel($dueAt, $bucket);
        }

        $dueDay = $dueAt->copy()->timezone(config('app.display_timezone'))->startOfDay();
        $today = now()->timezone(config('app.display_timezone'))->startOfDay();
        $tomorrow = $today->copy()->addDay();

        return match (true) {
            $dueDay->equalTo($today) => 'Due today',
            $dueDay->equalTo($tomorrow) => 'Due tomorrow',
            default => 'Due '.$dueDay->format('D M j'),
        };
    }

    private function dueRelativeLabel(?Carbon $dueAt, ?string $bucket = null): string
    {
        if (! $dueAt instanceof Carbon) {
            return '';
        }

        $bucket ??= $this->bucket($dueAt);

        if ($bucket === 'overdue') {
            $days = max(1, (int) $dueAt->copy()->timezone(config('app.display_timezone'))->startOfDay()->diffInDays(
                now()->timezone(config('app.display_timezone'))->startOfDay(),
            ));

            return $days === 1 ? 'Overdue 1 day' : 'Overdue '.$days.' days';
        }

        return $this->scheduleLabel($dueAt, $bucket);
    }
}
