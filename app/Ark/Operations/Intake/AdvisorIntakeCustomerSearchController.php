<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Customers\CustomerSearchQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdvisorIntakeCustomerSearchController
{
    public function __invoke(Request $request): View
    {
        $searchQuery = trim((string) $request->query('q', ''));

        return view('operations.intake.partials.customer-search-results', [
            'searchQuery' => $searchQuery,
            'searchCustomers' => CustomerSearchQuery::matching($searchQuery),
        ]);
    }
}
