<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountsReceivableVoidPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CustomerPaymentReceipt $receipt,
        VoidCustomerPaymentReceiptAction $voidReceipt,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::FinancialManage->value),
            403,
        );

        abort_unless((int) $receipt->customer_id === (int) $customer->id, 404);

        $voidReceipt->execute($receipt, $request->user());

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with('status', 'Payment receipt voided. Allocations were reversed.');
    }
}
