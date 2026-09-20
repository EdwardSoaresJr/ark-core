<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\Today\TodayPipelineInventoryQuery;
use App\Ark\Operations\Workboard\WorkboardAttentionInventoryQuery;
use App\Ark\Operations\Workboard\WorkboardInventoryContext;
use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RepairOrderIndexController
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = RepairOrderStatus::tryFrom((string) $request->query('status', ''));
        $dispositionFilter = RepairOrderConcernDisposition::tryFrom((string) $request->query('disposition', ''));
        $openQueueFilter = $request->query('open') === '1';
        $pickupFilter = (string) $request->query('pickup', '');
        $laneFilter = (string) $request->query('lane', '');
        $attentionFilter = (string) $request->query('attention', '');
        $pipelineFilter = (string) $request->query('pipeline', '');
        $unassignedFilter = $request->query('unassigned') === '1';
        $createdFrom = $request->date('created_from');
        $createdTo = $request->date('created_to');
        $collectedFrom = $request->date('collected_from');
        $collectedTo = $request->date('collected_to');

        $repairOrders = RepairOrder::query()
            ->with(['customer', 'vehicle'])
            ->when($search !== '', function (Builder $repairOrders) use ($search): void {
                $like = '%'.$search.'%';
                $repairOrderNumber = ltrim($search, '#');

                $repairOrders->where(function (Builder $repairOrders) use ($like, $repairOrderNumber): void {
                    if (ctype_digit($repairOrderNumber)) {
                        $repairOrders->orWhere('repair_order_id', (int) $repairOrderNumber);
                    }

                    $repairOrders
                        ->orWhere('concern_summary', 'like', $like)
                        ->orWhereHas('customer', function (Builder $customers) use ($like): void {
                            $customers
                                ->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })
                        ->orWhereHas('vehicle', function (Builder $vehicles) use ($like): void {
                            $vehicles
                                ->where('vin', 'like', $like)
                                ->orWhere('normalized_vin', 'like', $like)
                                ->orWhere('plate', 'like', $like)
                                ->orWhere('year', 'like', $like)
                                ->orWhere('make', 'like', $like)
                                ->orWhere('model', 'like', $like)
                                ->orWhere('trim', 'like', $like);
                        });
                });
            })
            ->when($status, fn (Builder $repairOrders): Builder => $repairOrders->where('status', $status->value))
            ->when($openQueueFilter, fn (Builder $repairOrders): Builder => $repairOrders->whereIn(
                'status',
                RepairOrderStatus::operationalQueueValues(),
            ))
            ->when(
                $dispositionFilter !== null,
                fn (Builder $repairOrders): Builder => $repairOrders->whereHas(
                    'concerns',
                    fn (Builder $concerns): Builder => $concerns->where('disposition', $dispositionFilter->value),
                ),
            )
            ->when($pickupFilter === 'stale', function (Builder $repairOrders): Builder {
                return $repairOrders
                    ->whereIn('status', [
                        RepairOrderStatus::Completed->value,
                        RepairOrderStatus::Invoiced->value,
                        RepairOrderStatus::ReadyPickup->value,
                    ])
                    ->where('updated_at', '<', now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS));
            })
            ->when($pickupFilter === 'all', fn (Builder $repairOrders): Builder => $repairOrders->whereIn('status', [
                RepairOrderStatus::Completed->value,
                RepairOrderStatus::Invoiced->value,
                RepairOrderStatus::ReadyPickup->value,
            ]))
            ->when($unassignedFilter, fn (Builder $repairOrders): Builder => $repairOrders
                ->whereIn('status', WorkboardSwimlaneCatalog::shopFloorSlugs())
                ->whereNull('assigned_technician_id'))
            ->when($laneFilter === 'shop_floor', fn (Builder $repairOrders): Builder => $repairOrders
                ->whereIn('status', WorkboardSwimlaneCatalog::shopFloorSlugs()))
            ->when(
                WorkboardAttentionInventoryQuery::label($attentionFilter) !== null,
                fn (Builder $repairOrders): Builder => WorkboardAttentionInventoryQuery::apply($repairOrders, $attentionFilter),
            )
            ->when(
                TodayPipelineInventoryQuery::label($pipelineFilter) !== null,
                fn (Builder $repairOrders): Builder => TodayPipelineInventoryQuery::apply(
                    $repairOrders,
                    $pipelineFilter,
                    $collectedFrom?->startOfDay(),
                    $collectedTo?->endOfDay(),
                ),
            )
            ->when($createdFrom, fn (Builder $repairOrders): Builder => $repairOrders->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo, fn (Builder $repairOrders): Builder => $repairOrders->whereDate('created_at', '<=', $createdTo))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('operations.repair-orders.index', [
            'createdFrom' => $createdFrom?->toDateString(),
            'createdTo' => $createdTo?->toDateString(),
            'query' => $search,
            'pickupFilter' => $pickupFilter !== '' ? $pickupFilter : null,
            'laneFilter' => $laneFilter !== '' ? $laneFilter : null,
            'attentionFilter' => WorkboardAttentionInventoryQuery::label($attentionFilter) !== null ? $attentionFilter : null,
            'pipelineFilter' => TodayPipelineInventoryQuery::label($pipelineFilter) !== null ? $pipelineFilter : null,
            'pipelineFilterLabel' => TodayPipelineInventoryQuery::label($pipelineFilter),
            'unassignedFilter' => $unassignedFilter,
            'workboardReturnUrl' => WorkboardInventoryContext::returnUrl(
                $pickupFilter !== '' ? $pickupFilter : null,
                $laneFilter !== '' ? $laneFilter : null,
                WorkboardAttentionInventoryQuery::label($attentionFilter) !== null ? $attentionFilter : null,
                $unassignedFilter,
            ),
            'repairOrders' => $repairOrders,
            'selectedStatus' => $status?->value,
            'dispositionFilter' => $dispositionFilter?->value,
            'openQueueFilter' => $openQueueFilter,
            'statusFilterOptions' => app(RepairOrderStatusCatalog::class)->filterOptions(),
        ]);
    }
}
