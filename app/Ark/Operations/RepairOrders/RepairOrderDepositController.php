<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\ManualDepositSubmission;
use App\Ark\Operations\Financial\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderDepositController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderConcurrency $concurrency,
        ManualDepositSubmission $deposits,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::in([
                PaymentMethod::Cash->value,
                PaymentMethod::Card->value,
                PaymentMethod::Check->value,
            ])],
            'reference' => ['nullable', 'string', 'max:255'],
            'deposit_confirmed' => ['accepted'],
            FinancialSubmissionIntentGate::FIELD => ['required', 'uuid'],
        ], [
            'deposit_confirmed.accepted' => 'Confirm that the customer paid before recording this deposit.',
        ]);

        $deposits->execute(
            $repairOrder,
            $request->user(),
            $data[FinancialSubmissionIntentGate::FIELD],
            $totalsCalculator->unitPriceCents($data['amount']),
            PaymentMethod::from($data['payment_method']),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            broadcastFinancialChange: true,
        );

        return redirect()
            ->back()
            ->with('status', 'Deposit recorded.');
    }
}
