<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class AccountsReceivableBatchDocumentsController extends Controller
{
    public function __invoke(
        Request $request,
        CustomerAccountStatementBuilder $statements,
        CustomerAccountStatementPdf $pdf,
        CustomerAddressLabelSheet $labels,
        CustomerAccountStatementEmailDelivery $email,
    ): RedirectResponse|Response|SymfonyResponse {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'distinct'],
            'action' => ['required', 'in:statements_print,statements_pdf,labels,email'],
        ]);

        $customers = Customer::query()
            ->whereIn('id', $data['customer_ids'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($customers->count() !== count($data['customer_ids'])) {
            throw ValidationException::withMessages([
                'customer_ids' => 'One or more selected accounts could not be found.',
            ]);
        }

        return match ($data['action']) {
            'statements_print' => response(view('operations.financials.accounts-receivable.statement-document', [
                'combined' => true,
                'statements' => $customers->map(fn (Customer $customer) => $statements->forCustomer($customer))->all(),
                'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
            ])->render())->header('Content-Type', 'text/html; charset=UTF-8'),
            'statements_pdf' => $this->combinedPdf($customers, $statements, $pdf),
            'labels' => response($labels->html($customers))->header('Content-Type', 'text/html; charset=UTF-8'),
            'email' => $this->emailAll($request, $customers, $email),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     */
    private function combinedPdf($customers, CustomerAccountStatementBuilder $statements, CustomerAccountStatementPdf $pdf): SymfonyResponse
    {
        try {
            $bytes = $pdf->combinedBytes(
                $customers->map(fn (Customer $customer) => $statements->forCustomer($customer))->all()
            );
        } catch (Throwable $exception) {
            return redirect()
                ->route('operations.financials.accounts-receivable.index')
                ->with('status', 'PDF runtime unavailable. Use printable statements instead. '.$exception->getMessage());
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="account-statements.pdf"',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     */
    private function emailAll(Request $request, $customers, CustomerAccountStatementEmailDelivery $email): RedirectResponse
    {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersCloseout->value)
                || $request->user()?->can(ArkCapability::FinancialManage->value),
            403,
        );

        $sent = 0;
        $skipped = [];

        foreach ($customers as $customer) {
            if (! $customer->hasEmail()) {
                $skipped[] = $customer->name;
                continue;
            }

            try {
                $email->send($customer, $request->user());
                $sent++;
            } catch (Throwable) {
                $skipped[] = $customer->name;
            }
        }

        $message = "Emailed {$sent} statement(s).";
        if ($skipped !== []) {
            $message .= ' Skipped: '.implode(', ', $skipped).'.';
        }

        return redirect()
            ->route('operations.financials.accounts-receivable.index')
            ->with('status', $message);
    }
}
