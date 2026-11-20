<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
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
        $receipts = CustomerPaymentReceipt::query()
            ->where('customer_id', $customer->id)
            ->with(['ledgerEntries.financialDocument', 'ledgerEntries.repairOrder'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $pendingCaptures = PaymentCaptureAttempt::query()
            ->where('customer_id', $customer->id)
            ->where('context_kind', PaymentCaptureContextKind::AccountsReceivable->value)
            ->where('status', PaymentCaptureAttemptStatus::ReconciliationRequired->value)
            ->whereNull('customer_payment_receipt_id')
            ->orderByDesc('id')
            ->get();

        return view('operations.financials.accounts-receivable.customer', [
            'customer' => $customer,
            'dashboard' => $dashboard,
            'receipts' => $receipts,
            'pendingCaptures' => $pendingCaptures,
            'canReceivePayment' => $request->user()?->can(ArkCapability::RepairOrdersCloseout->value)
                || $request->user()?->can(ArkCapability::FinancialManage->value),
            'canVoidPayment' => (bool) $request->user()?->can(ArkCapability::FinancialManage->value),
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }
}
