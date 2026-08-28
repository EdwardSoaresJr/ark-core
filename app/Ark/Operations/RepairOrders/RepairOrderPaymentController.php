<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderPaymentController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLedgerPaymentRecorder $payments,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::in([
                PaymentMethod::Cash->value,
                PaymentMethod::Card->value,
                PaymentMethod::Check->value,
            ])],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $amountCents = $totalsCalculator->unitPriceCents($data['amount']);

        $payments->record(
            $repairOrder,
            $amountCents,
            PaymentMethod::from($data['payment_method']),
            $request->user(),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            RepairOrderPaymentPaidAt::fromDateInput($data['paid_at'] ?? null),
        );

        return redirect()
            ->back()
            ->with('status', 'Payment recorded.');
    }
}
