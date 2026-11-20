<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountsReceivableCustomerController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        AccountsReceivableOutstandingQuery $query,
    ): View {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        $dashboard = $query->customerAccount($customer);

        return view('operations.financials.accounts-receivable.customer', [
            'customer' => $customer,
            'dashboard' => $dashboard,
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }
}
