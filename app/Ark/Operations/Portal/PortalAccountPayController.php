<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\AccountsReceivableOutstandingQuery;
use App\Ark\Operations\Financial\CustomerAccountStatementBuilder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\InitiateAccountsReceivableCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\CardPresentCaptureProjection;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Portal multi-invoice pay using the same ARK Pay capture + AR allocation path as staff.
 */
final class PortalAccountPayController extends Controller
{
    public function create(
        CustomerAccountStatementBuilder $statements,
        AccountsReceivableOutstandingQuery $query,
        CardPresentCaptureProjection $cardPresent,
    ): View {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();
        $config = $cardPresent->publicConfig();

        abort_unless((bool) ($config['portalPayEnabled'] ?? false), 403, 'Online account payments are not enabled for this shop.');

        return view('portal.account-pay', [
            'customer' => $customer,
            'statement' => $statements->forCustomer($customer),
            'invoices' => $query->customerAccount($customer)['invoices'],
            'publicConfig' => $config,
            'webPaymentsPublicConfig' => $cardPresent->webPaymentsPublicConfig(),
            'intentKey' => (string) Str::uuid(),
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }

    public function store(
        Request $request,
        InitiateAccountsReceivableCaptureAction $capture,
        EstimateTotalsCalculator $totals,
        CardPresentCaptureProjection $cardPresent,
    ): RedirectResponse {
        /** @var Customer $customer */
        $customer = Auth::guard('portal')->user();
        $config = $cardPresent->publicConfig();

        abort_unless((bool) ($config['portalPayEnabled'] ?? false), 403, 'Online account payments are not enabled for this shop.');

        $data = $request->validate([
            'source_token' => ['required', 'string', 'max:191'],
            'intent_key' => ['required', 'uuid'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.selected' => ['nullable', 'boolean'],
            'stub_scenario' => ['nullable', 'string', 'max:64'],
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

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Select at least one invoice to pay.',
            ]);
        }

        if (! app()->environment('local', 'testing')) {
            $token = strtolower(trim((string) $data['source_token']));
            if (str_starts_with($token, 'tok_stub') || filled($data['stub_scenario'] ?? null)) {
                throw ValidationException::withMessages([
                    'source_token' => 'Live portal payments require a Platform Web Payments source token. Test stubs are not accepted.',
                ]);
            }
        }

        $result = $capture->execute(
            $customer,
            $allocations,
            null,
            [
                'capture_method' => PaymentCaptureMethod::Keyed,
                'source_token' => $data['source_token'],
                'intent_key' => $data['intent_key'],
                'reference' => 'portal-ar',
                'stub_scenario' => app()->environment('local', 'testing') ? ($data['stub_scenario'] ?? null) : null,
            ],
        );

        $attempt = $result['attempt'];
        if ($attempt->customer_payment_receipt_id === null) {
            return redirect()
                ->route('portal.account.pay')
                ->with('status', 'Payment '.$attempt->status->value.'. Please contact the shop if your card was charged.');
        }

        return redirect()
            ->route('portal.account.statement')
            ->with('status', 'Payment received. Thank you.');
    }
}
