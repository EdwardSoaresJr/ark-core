<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Settings\ShopSettings;
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

        $customers = Customer::query()
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
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(24)
            ->withQueryString();

        return view('operations.customers.search', [
            'customers' => $customers,
            'query' => $query,
            'selectedType' => $type,
            'createdFrom' => $createdFrom?->toDateString(),
            'createdTo' => $createdTo?->toDateString(),
            'hasFilters' => $hasFilters,
            'intakeMode' => $request->boolean('intake'),
            'customerTypes' => $customerTypes,
        ]);
    }
}
