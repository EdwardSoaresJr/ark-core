<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentPaidAt;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AccountsReceivableReceivePaymentController extends Controller
{
    public function create(
        Request $request,
        Customer $customer,
        AccountsReceivableOutstandingQuery $query,
    ): View {
        abort_unless($request->user()?->can(ArkCapability::FinancialView->value), 403);

        $dashboard = $query->customerAccount($customer);

        return view('operations.financials.accounts-receivable.receive-payment', [
            'customer' => $customer,
            'invoices' => $dashboard['invoices'],
            'methods' => array_merge(
                [['key' => PaymentMethod::Cash->value, 'label' => PaymentMethod::Cash->label()]],
                ManualPaymentMethods::current(),
            ),
            'intentKey' => (string) str()->uuid(),
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }

    public function store(
        Request $request,
        Customer $customer,
        AllocateCustomerPaymentAction $allocate,
        EstimateTotalsCalculator $totals,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersCloseout->value)
                || $request->user()?->can(ArkCapability::FinancialManage->value),
            403,
        );

        $methodKeys = array_merge(
            [PaymentMethod::Cash->value],
            ManualPaymentMethods::keys(),
        );

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::in($methodKeys)],
            'reference' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            WorksheetMutationIdempotency::FIELD => ['required', 'uuid'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.selected' => ['nullable', 'boolean'],
        ]);

        $allocations = [];
        foreach ($data['allocations'] as $row) {
            if (! ($row['selected'] ?? false)) {
                continue;
            }

            $amount = trim((string) ($row['amount'] ?? ''));
            if ($amount === '' || (float) $amount <= 0) {
                continue;
            }

            $allocations[] = [
                'invoice_id' => (int) $row['invoice_id'],
                'amount_cents' => $totals->unitPriceCents($amount),
            ];
        }

        $receipt = $allocate->execute(
            $customer,
            $totals->unitPriceCents($data['amount']),
            PaymentMethod::tryFrom($data['payment_method']) ?? $data['payment_method'],
            $allocations,
            $data[WorksheetMutationIdempotency::FIELD],
            $request->user(),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            RepairOrderPaymentPaidAt::fromDateInput($data['paid_at'] ?? null),
        );

        $message = 'Payment recorded: $'.number_format($receipt->amount_cents / 100, 2)
            .' · $'.number_format($receipt->allocated_cents / 100, 2).' applied across '.count($allocations).' invoice(s).';

        if ($receipt->change_cents > 0) {
            $message .= ' Change returned: $'.number_format($receipt->change_cents / 100, 2).'.';
        }

        if ($receipt->unapplied_cents > 0) {
            $message .= ' $'.number_format($receipt->unapplied_cents / 100, 2).' posted as store credit.';
        }

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with('status', $message);
    }
}
