<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AccountsReceivableReconcileCaptureController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        PaymentCaptureAttempt $attempt,
        ReconcileAccountsReceivableCaptureAction $reconcile,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersCloseout->value)
                || $request->user()?->can(ArkCapability::FinancialManage->value),
            403,
        );

        abort_unless(
            (int) $attempt->customer_id === (int) $customer->id
                && $attempt->context_kind === PaymentCaptureContextKind::AccountsReceivable,
            404,
        );

        try {
            $reconciled = $reconcile->execute($attempt, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('operations.financials.accounts-receivable.customer', $customer)
                ->with('status', $exception->getMessage());
        }

        $message = $reconciled->status === PaymentCaptureAttemptStatus::Succeeded
            ? 'Card capture '.$reconciled->public_id.' allocated to invoices without charging again.'
            : 'Capture '.$reconciled->public_id.' is still '.$reconciled->status->value.'.';

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with('status', $message);
    }
}
