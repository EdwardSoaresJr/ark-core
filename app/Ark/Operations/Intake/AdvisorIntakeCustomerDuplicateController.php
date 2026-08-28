<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Customers\CustomerDuplicateQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdvisorIntakeCustomerDuplicateController
{
    public function __invoke(Request $request): View
    {
        return view('operations.intake.partials.customer-duplicate-results', [
            'duplicateMatches' => CustomerDuplicateQuery::potentialDuplicates(
                firstName: (string) $request->query('first_name', ''),
                lastName: (string) $request->query('last_name', ''),
                phone: (string) $request->query('phone', ''),
                email: (string) $request->query('email', ''),
                excludeCustomerId: $request->integer('exclude_customer_id') ?: null,
            ),
        ]);
    }
}
