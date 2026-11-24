<?php

namespace App\Ark\Operations\Vehicles;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Preferences\CollectionLayoutModule;
use App\Ark\Runtime\Preferences\CollectionLayoutPreference;
use App\Ark\Ui\Collection\CollectionSortState;
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
        $layoutPreference = CollectionLayoutPreference::resolve($request, CollectionLayoutModule::Vehicles);

        $sort = CollectionSortState::fromRequest(
            $request,
            allowed: ['vehicle', 'customer', 'plate', 'vin', 'updated'],
            defaultKey: 'updated',
            defaultDirs: [
                'vehicle' => 'asc',
                'customer' => 'asc',
                'plate' => 'asc',
                'vin' => 'asc',
                'updated' => 'desc',
            ],
            columnMap: [
                'vehicle' => static function (Builder $vehicles, string $dir): void {
                    $vehicles
                        ->orderBy('year', $dir)
                        ->orderBy('make', $dir)
                        ->orderBy('model', $dir);
                },
                'customer' => static function (Builder $vehicles, string $dir): void {
                    $vehicles->orderBy(
                        \App\Ark\Operations\Customers\Customer::query()
                            ->select('last_name')
                            ->whereColumn('customers.id', 'vehicles.customer_id')
                            ->limit(1),
                        $dir,
                    )->orderBy(
                        \App\Ark\Operations\Customers\Customer::query()
                            ->select('first_name')
                            ->whereColumn('customers.id', 'vehicles.customer_id')
                            ->limit(1),
                        $dir,
                    );
                },
                'plate' => static fn (Builder $vehicles, string $dir): Builder => $vehicles->orderBy('plate', $dir),
                'vin' => static fn (Builder $vehicles, string $dir): Builder => $vehicles->orderBy('vin', $dir),
                'updated' => static fn (Builder $vehicles, string $dir): Builder => $vehicles->orderBy('updated_at', $dir),
            ],
        );

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
            ->tap(fn (Builder $vehicles) => $sort->apply($vehicles))
            ->paginate(24)
            ->withQueryString();

        $filterQuery = array_filter([
            'q' => $query !== '' ? $query : null,
            'work' => $work !== '' ? $work : null,
            'created_from' => $createdFrom?->toDateString(),
            'created_to' => $createdTo?->toDateString(),
            'view' => $layoutPreference['explicit'] ? $layoutPreference['mode'] : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return view('operations.vehicles.search', [
            'vehicles' => $vehicles,
            'query' => $query,
            'selectedWork' => $work,
            'createdFrom' => $createdFrom?->toDateString(),
            'createdTo' => $createdTo?->toDateString(),
            'hasFilters' => $hasFilters,
            'collectionSort' => $sort,
            'collectionFilterQuery' => $filterQuery,
            'collectionLayoutMode' => $layoutPreference['mode'],
            'collectionLayoutExplicit' => $layoutPreference['explicit'],
            'collectionLayoutModule' => CollectionLayoutModule::Vehicles,
        ]);
    }
}
