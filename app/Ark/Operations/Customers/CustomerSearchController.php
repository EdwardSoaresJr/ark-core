<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Preferences\CollectionLayoutModule;
use App\Ark\Runtime\Preferences\CollectionLayoutPreference;
use App\Ark\Runtime\Preferences\UiPreferenceScope;
use App\Ark\Ui\Collection\CollectionSortState;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerSearchController
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $customerTypes = ShopSettings::current()->customerTypeRows();
        $allowedTypes = collect($customerTypes)
            ->map(fn (array $row): string => (string) ($row['name'] ?? ''))
            ->filter()
            ->values()
            ->all();
        $type = (string) $request->query('type', '');
        if ($type !== '' && ! in_array($type, $allowedTypes, true)) {
            $type = '';
        }
        $createdFrom = $request->date('created_from');
        $createdTo = $request->date('created_to');
        $hasFilters = $query !== '' || $type !== '' || $createdFrom !== null || $createdTo !== null;
        $intakeMode = $request->boolean('intake');
        $layoutPreference = CollectionLayoutPreference::resolve($request, CollectionLayoutModule::Customers);

        $sort = CollectionSortState::fromRequest(
            $request,
            allowed: ['name', 'phone', 'email', 'type', 'vehicles', 'updated'],
            defaultKey: 'name',
            defaultDirs: [
                'name' => 'asc',
                'phone' => 'asc',
                'email' => 'asc',
                'type' => 'asc',
                'vehicles' => 'desc',
                'updated' => 'desc',
            ],
            columnMap: [
                'name' => static function (Builder $customers, string $dir): void {
                    $customers->orderBy('last_name', $dir)->orderBy('first_name', $dir);
                },
                'phone' => static fn (Builder $customers, string $dir): Builder => $customers->orderBy('phone', $dir),
                'email' => static fn (Builder $customers, string $dir): Builder => $customers->orderBy('email', $dir),
                'type' => static fn (Builder $customers, string $dir): Builder => $customers->orderBy('customer_type', $dir),
                'vehicles' => static fn (Builder $customers, string $dir): Builder => $customers->orderBy('vehicles_count', $dir),
                'updated' => static fn (Builder $customers, string $dir): Builder => $customers->orderBy('updated_at', $dir),
            ],
        );

        $customers = Customer::query()
            ->withCount([
                'vehicles',
                'repairOrders as open_repair_orders_count' => fn (Builder $repairOrders): Builder => $repairOrders
                    ->whereIn('status', RepairOrderStatus::operationalQueueValues()),
            ])
            ->tap(fn (Builder $customers) => CustomerSearchQuery::withOperationalContext($customers))
            ->when(
                $query !== '',
                fn (Builder $customers) => $customers->tap(
                    fn (Builder $customers) => CustomerSearchQuery::applyConstraints($customers, $query)
                ),
            )
            ->when($type !== '', fn (Builder $customers): Builder => $customers->where('customer_type', $type))
            ->when(
                $createdFrom instanceof Carbon,
                fn (Builder $customers): Builder => $customers->whereDate('created_at', '>=', $createdFrom),
            )
            ->when(
                $createdTo instanceof Carbon,
                fn (Builder $customers): Builder => $customers->whereDate('created_at', '<=', $createdTo),
            )
            ->tap(fn (Builder $customers) => $sort->apply($customers))
            ->paginate(24)
            ->withQueryString();

        $filterQuery = array_filter([
            'q' => $query !== '' ? $query : null,
            'type' => $type !== '' ? $type : null,
            'created_from' => $createdFrom?->toDateString(),
            'created_to' => $createdTo?->toDateString(),
            'intake' => $intakeMode ? 1 : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return view('operations.customers.search', [
            'customers' => $customers,
            'query' => $query,
            'selectedType' => $type,
            'createdFrom' => $createdFrom?->toDateString(),
            'createdTo' => $createdTo?->toDateString(),
            'hasFilters' => $hasFilters,
            'intakeMode' => $intakeMode,
            'customerTypes' => $customerTypes,
            'collectionSort' => $sort,
            'collectionFilterQuery' => $filterQuery,
            'collectionLayoutMode' => $layoutPreference['mode'],
            'collectionLayoutExplicit' => $layoutPreference['explicit'],
            'collectionLayoutModule' => CollectionLayoutModule::Customers,
            'uiPreferenceScope' => $layoutPreference['scope'] ?? UiPreferenceScope::forRequest($request),
        ]);
    }
}
