<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\CustomerAccountStatementBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Permission-scoped portal read of the same AR statement payload used by staff.
 */
final class PortalAccountStatementController extends Controller
{
    public function __invoke(CustomerAccountStatementBuilder $statements): View
    {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();

        return view('portal.account-statement', [
            'customer' => $customer,
            'statement' => $statements->forCustomer($customer),
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }
}
