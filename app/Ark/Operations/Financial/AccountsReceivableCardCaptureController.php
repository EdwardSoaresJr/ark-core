<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\CardPresentCaptureProjection;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AccountsReceivableCardCaptureController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        InitiateAccountsReceivableCaptureAction $capture,
        EstimateTotalsCalculator $totals,
        CardPresentCaptureProjection $cardPresent,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersCloseout->value)
                || $request->user()?->can(ArkCapability::FinancialManage->value),
            403,
        );

        $config = $cardPresent->publicConfig();
        $allowedMethods = [];
        if ($config['keyedEnabled'] ?? false) {
            $allowedMethods[] = PaymentCaptureMethod::Keyed->value;
        }
        if ($config['terminalEnabled'] ?? false) {
            $allowedMethods[] = PaymentCaptureMethod::Terminal->value;
        }

        if ($allowedMethods === []) {
            throw ValidationException::withMessages([
                'capture' => 'Card capture is not available for this shop. Use cash, check, or external card.',
            ]);
        }

        $data = $request->validate([
            'capture_method' => ['required', Rule::in($allowedMethods)],
            'source_token' => ['nullable', 'string', 'max:191'],
            'device_ref' => ['nullable', 'string', 'max:191'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:128'],
            'disposition' => ['nullable', Rule::in(array_map(
                fn (OverpaymentDisposition $disposition): string => $disposition->value,
                OverpaymentDisposition::cases(),
            ))],
            'stub_scenario' => ['nullable', 'string', 'max:64'],
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

        $result = $capture->execute(
            $customer,
            $allocations,
            $request->user(),
            [
                'capture_method' => PaymentCaptureMethod::from($data['capture_method']),
                'source_token' => $data['source_token'] ?? null,
                'device_ref' => $data['device_ref'] ?? null,
                'stub_scenario' => app()->environment('local', 'testing') ? ($data['stub_scenario'] ?? null) : null,
                'paid_on' => $data['paid_at'] ?? null,
                'intent_key' => $data[WorksheetMutationIdempotency::FIELD],
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'disposition' => OverpaymentDisposition::tryFrom((string) ($data['disposition'] ?? ''))
                    ?? OverpaymentDisposition::KeepCredit,
            ],
        );

        $attempt = $result['attempt'];

        if ($attempt->status === PaymentCaptureAttemptStatus::ReconciliationRequired) {
            return redirect()
                ->route('operations.financials.accounts-receivable.customer', $customer)
                ->with(
                    'status',
                    'Card was charged ('.$attempt->public_id.'), but invoice allocation needs reconciliation. '
                    .'Do not charge again — use Retry allocation on the account page.',
                );
        }

        if ($attempt->customer_payment_receipt_id === null && ! $attempt->hasLedgerEntry()) {
            return redirect()
                ->route('operations.financials.accounts-receivable.receive-payment.create', $customer)
                ->with('status', 'Card capture '.$attempt->status->value.'. Public id '.$attempt->public_id);
        }

        return redirect()
            ->route('operations.financials.accounts-receivable.customer', $customer)
            ->with('status', 'Card payment captured and allocated across the selected invoices.');
    }
}
