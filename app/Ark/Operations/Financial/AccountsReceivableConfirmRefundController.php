<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountsReceivableConfirmRefundController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CustomerPaymentReceipt $receipt,
        ConfirmCustomerPaymentRefundAction $confirm,
    ): RedirectResponse {
        abort_unless($request->user()?->can(ArkCapability::FinancialManage->value), 403);
        abort_unless((int) $receipt->customer_id === (int) $customer->id, 404);

        $data = $request->validate([
            'evidence' => ['required', 'string', 'max:191'],
        ]);

        $pendingCents = (int) $receipt->refund_pending_cents;
        $confirm->execute($receipt, $data['evidence'], $request->user());

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with(
                'status',
                'Confirmed pending refund of $'.number_format($pendingCents / 100, 2)
                .' with evidence '.$data['evidence'].'.',
            );
    }
}
