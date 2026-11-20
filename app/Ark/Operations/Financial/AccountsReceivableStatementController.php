<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class AccountsReceivableStatementController extends Controller
{
    public function show(
        Request $request,
        Customer $customer,
        CustomerAccountStatementBuilder $statements,
    ): View {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        return view('operations.financials.accounts-receivable.statement', [
            'customer' => $customer,
            'statement' => $statements->forCustomer($customer),
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }

    public function pdf(
        Request $request,
        Customer $customer,
        CustomerAccountStatementBuilder $statements,
        CustomerAccountStatementPdf $pdf,
    ): SymfonyResponse {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        try {
            $bytes = $pdf->bytes($statements->forCustomer($customer));
        } catch (Throwable $exception) {
            return redirect()
                ->route('operations.financials.accounts-receivable.statement.show', $customer)
                ->with('status', 'PDF runtime unavailable. Use Print Statement instead. '.$exception->getMessage());
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="statement-customer-'.$customer->id.'.pdf"',
        ]);
    }

    public function email(
        Request $request,
        Customer $customer,
        CustomerAccountStatementEmailDelivery $delivery,
    ): SymfonyResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersCloseout->value)
                || $request->user()?->can(ArkCapability::FinancialManage->value),
            403,
        );

        $delivery->send($customer, $request->user());

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with('status', 'Statement emailed to '.$customer->email.'.');
    }

    public function label(
        Request $request,
        Customer $customer,
        CustomerAddressLabelSheet $labels,
    ): Response {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        return response($labels->html([$customer]))->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
