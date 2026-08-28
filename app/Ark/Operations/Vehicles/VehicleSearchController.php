<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VehicleSearchController
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $work = (string) $request->query('work', '');
        if (! in_array($work, ['open', 'idle'], true)) {
            $work = '';
        }
        $createdFrom = $request->date('created_from');
        $createdTo = $request->date('created_to');
        $hasFilters = $query !== '' || $work !== '' || $createdFrom !== null || $createdTo !== null;
        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        $vehicles = Vehicle::query()
            ->tap(fn (Builder $vehicles) => VehicleSearchQuery::withOperationalContext($vehicles))
            ->when(
                $query !== '',
                fn (Builder $vehicles) => $vehicles->tap(
                    fn (Builder $vehicles) => VehicleSearchQuery::applyConstraints($vehicles, $query)
                ),
            )
            ->when(
                $work === 'open',
                fn (Builder $vehicles): Builder => $vehicles->whereHas(
                    'repairOrders',
                    fn (Builder $repairOrders) => $repairOrders->whereIn('status', $activeStatuses),
                ),
            )
            ->when(
                $work === 'idle',
                fn (Builder $vehicles): Builder => $vehicles->whereDoesntHave(
                    'repairOrders',
                    fn (Builder $repairOrders) => $repairOrders->whereIn('status', $activeStatuses),
                ),
            )
            ->when(
                $createdFrom instanceof Carbon,
                fn (Builder $vehicles): Builder => $vehicles->whereDate('created_at', '>=', $createdFrom),
            )
            ->when(
                $createdTo instanceof Carbon,
                fn (Builder $vehicles): Builder => $vehicles->whereDate('created_at', '<=', $createdTo),
            )
            ->orderByDesc('updated_at')
            ->paginate(24)
            ->withQueryString();

        return view('operations.vehicles.search', [
            'vehicles' => $vehicles,
            'query' => $query,
            'selectedWork' => $work,
            'createdFrom' => $createdFrom?->toDateString(),
            'createdTo' => $createdTo?->toDateString(),
            'hasFilters' => $hasFilters,
        ]);
    }
}
