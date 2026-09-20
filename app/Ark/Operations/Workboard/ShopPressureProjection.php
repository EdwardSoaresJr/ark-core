<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

final class ShopPressureProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return list<array{
     *     label: string,
     *     value: int,
     *     hint: string,
     *     tone: string,
     *     url: string,
     * }>
     */
    public function cardsFor(User $user): array
    {
        $lens = WorkboardLens::forUser($user);
        $repairOrders = $this->loadRepairOrders($user, $lens);
        $repairOrdersByStatus = $repairOrders->groupBy(
            fn (RepairOrder $repairOrder): string => $repairOrder->workboardLaneStatus()->value,
        );
        $repairOrderTotals = $repairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $this->totalsCalculator->totalsFor($repairOrder),
        ]);

        if ($lens === WorkboardLens::TECHNICIAN) {
            return $this->technicianCards($repairOrders, $repairOrdersByStatus, $repairOrderTotals, $user);
        }

        return $this->advisorCards($repairOrdersByStatus, $repairOrderTotals);
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function loadRepairOrders(User $user, string $lens): Collection
    {
        $repairOrders = RepairOrder::query()
            ->with([
                'customer',
                'vehicle',
                'assignedTechnician:id,name',
                'lines.concern:id,disposition',
            ])
            ->whereIn('status', WorkboardLens::queueStatusValues($lens))
            ->latest()
            ->get();

        return WorkboardLens::filterRepairOrders($repairOrders, $lens, $user);
    }

    /**
     * @param  Collection<string, Collection<int, RepairOrder>>  $repairOrdersByStatus
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     * @return list<array{label: string, value: int, hint: string, tone: string, url: string}>
     */
    private function advisorCards(Collection $repairOrdersByStatus, Collection $repairOrderTotals): array
    {
        $waitingApproval = $this->ordersInStatuses($repairOrdersByStatus, [
            RepairOrderStatus::WaitingApproval,
            RepairOrderStatus::WaitingApproval,
        ]);
        $waitingParts = $repairOrdersByStatus->get(RepairOrderStatus::WaitingParts->value, collect());
        $shopFloor = $this->ordersInStatuses($repairOrdersByStatus, [
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
            RepairOrderStatus::InProgress,
        ]);
        $qualityCheck = $repairOrdersByStatus->get(RepairOrderStatus::QualityCheck->value, collect());
        $readyPickup = $this->ordersInStatuses($repairOrdersByStatus, [
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup,
        ]);
        $activeWork = $repairOrdersByStatus->get(RepairOrderStatus::InProgress->value, collect());
        $readyForExecution = $this->ordersInStatuses($repairOrdersByStatus, [
            RepairOrderStatus::ReadyForWork,
            RepairOrderStatus::Approved,
        ]);
        $unassigned = $readyForExecution
            ->merge($activeWork)
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->assigned_technician_id === null)
            ->count();
        $waitingPartCounts = $this->aggregateWaitingPartCounts($waitingParts);
        $partsBlockerHint = collect([
            'need ordered' => $waitingPartCounts['needs_ordered'],
            'sourcing' => $waitingPartCounts['sourcing'],
            'ordered' => $waitingPartCounts['ordered'],
            'partial' => $waitingPartCounts['partial'],
            'backordered' => $waitingPartCounts['backordered'],
        ])
            ->filter(fn (int $count): bool => $count > 0)
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->values()
            ->join(' · ');

        return [
            [
                'label' => 'Waiting Approval',
                'value' => $waitingApproval->count(),
                'hint' => $this->money($this->totalCents($waitingApproval, $repairOrderTotals)).' waiting customer',
                'tone' => 'approval',
                'url' => $this->workboardUrl('waiting-approval'),
            ],
            [
                'label' => 'Waiting Parts',
                'value' => $waitingParts->count(),
                'hint' => $partsBlockerHint !== '' ? $partsBlockerHint : 'blocked work / vendor dependency',
                'tone' => 'blocked',
                'url' => $this->workboardUrl('waiting-parts'),
            ],
            [
                'label' => 'Shop Floor',
                'value' => $shopFloor->count(),
                'hint' => $unassigned.' unassigned · active bay work',
                'tone' => 'motion',
                'url' => $this->workboardUrl('shop-floor'),
            ],
            [
                'label' => 'Quality Check',
                'value' => $qualityCheck->count(),
                'hint' => 'final checks before handoff',
                'tone' => 'ready',
                'url' => $this->workboardUrl('quality-check'),
            ],
            [
                'label' => 'Ready Pickup',
                'value' => $readyPickup->count(),
                'hint' => $this->money($this->totalCents($readyPickup, $repairOrderTotals)).' invoice / pickup',
                'tone' => 'ready',
                'url' => $this->workboardUrl('ready-pickup'),
            ],
        ];
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  Collection<string, Collection<int, RepairOrder>>  $repairOrdersByStatus
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     * @return list<array{label: string, value: int, hint: string, tone: string, url: string}>
     */
    private function technicianCards(Collection $repairOrders, Collection $repairOrdersByStatus, Collection $repairOrderTotals, User $user): array
    {
        $readyToStart = $this->ordersInStatuses($repairOrdersByStatus, [
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
        ]);
        $waitingParts = $repairOrdersByStatus->get(RepairOrderStatus::WaitingParts->value, collect());
        $myBay = $repairOrdersByStatus->get(RepairOrderStatus::InProgress->value, collect());
        $qualityCheck = $repairOrdersByStatus->get(RepairOrderStatus::QualityCheck->value, collect());

        return [
            [
                'label' => 'Ready to Start',
                'value' => $readyToStart->count(),
                'hint' => $readyToStart->whereNull('assigned_technician_id')->count().' unassigned',
                'tone' => 'motion',
                'url' => $this->workboardUrl('ready-to-start'),
            ],
            [
                'label' => 'Waiting Parts',
                'value' => $waitingParts->count(),
                'hint' => 'shop procurement blockers',
                'tone' => 'blocked',
                'url' => $this->workboardUrl('waiting-parts'),
            ],
            [
                'label' => 'My Bay',
                'value' => $myBay->count(),
                'hint' => $this->money($this->totalCents($myBay, $repairOrderTotals)).' active labor',
                'tone' => 'motion',
                'url' => $this->workboardUrl('my-bay'),
            ],
            [
                'label' => 'Quality Check',
                'value' => $qualityCheck->count(),
                'hint' => 'final checks before handoff',
                'tone' => 'ready',
                'url' => $this->workboardUrl('quality-check'),
            ],
        ];
    }

    /**
     * @param  Collection<string, Collection<int, RepairOrder>>  $repairOrdersByStatus
     * @param  list<RepairOrderStatus>  $statuses
     * @return Collection<int, RepairOrder>
     */
    private function ordersInStatuses(Collection $repairOrdersByStatus, array $statuses): Collection
    {
        return collect($statuses)
            ->flatMap(fn (RepairOrderStatus $status): Collection => $repairOrdersByStatus->get($status->value, collect()))
            ->values();
    }

    /**
     * @param  Collection<int, RepairOrder>  $orders
     * @param  Collection<int, EstimateTotals>  $repairOrderTotals
     */
    private function totalCents(Collection $orders, Collection $repairOrderTotals): int
    {
        return (int) $orders->sum(
            fn (RepairOrder $repairOrder): int => $repairOrderTotals[$repairOrder->id]?->totalCents() ?? 0,
        );
    }

    /**
     * @param  Collection<int, RepairOrder>  $waitingParts
     * @return array{needs_ordered: int, sourcing: int, ordered: int, partial: int, backordered: int}
     */
    private function aggregateWaitingPartCounts(Collection $waitingParts): array
    {
        $totals = [
            'needs_ordered' => 0,
            'sourcing' => 0,
            'ordered' => 0,
            'partial' => 0,
            'backordered' => 0,
        ];

        foreach ($waitingParts as $repairOrder) {
            $counts = $repairOrder->approvedPartReadinessCounts();

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + ($counts[$key] ?? 0);
            }
        }

        return $totals;
    }

    private function money(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }

    private function workboardUrl(string $laneSlug): string
    {
        $base = Route::has('operations.workboard')
            ? route('operations.workboard')
            : route('operations.index');

        return $base.'#ops-lane-'.$laneSlug;
    }
}
