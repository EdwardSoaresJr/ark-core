<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountsReceivableSettleProcessorRefundController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CustomerPaymentReceipt $receipt,
        SettleCustomerPaymentProcessorRefundAction $settle,
    ): RedirectResponse {
        abort_unless($request->user()?->can(ArkCapability::FinancialManage->value), 403);
        abort_unless((int) $receipt->customer_id === (int) $customer->id, 404);

        $pendingCents = (int) $receipt->refund_pending_cents;
        $result = $settle->execute($receipt, $request->user());

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with(
                'status',
                'Processor refund of $'.number_format($pendingCents / 100, 2)
                .' settled ('.$result['provider_refund_id'].').',
            );
    }
}
