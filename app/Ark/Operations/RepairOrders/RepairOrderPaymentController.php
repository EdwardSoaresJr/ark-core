<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\ManualPaymentMethods;
use App\Ark\Operations\Financial\ManualPaymentSubmission;
use App\Ark\Operations\Financial\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderPaymentController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderConcurrency $concurrency,
        FinancialSubmissionIntentGate $intents,
        ManualPaymentSubmission $payments,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::in(array_merge(
                [PaymentMethod::Cash->value],
                ManualPaymentMethods::keys(),
            ))],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            FinancialSubmissionIntentGate::FIELD => ['required', 'uuid'],
        ]);

        $paidAt = RepairOrderPaymentPaidAt::fromDateInput($data['paid_at'] ?? null);

        $payments->execute(
            $repairOrder,
            $request->user(),
            $data[FinancialSubmissionIntentGate::FIELD],
            $totalsCalculator->unitPriceCents($data['amount']),
            PaymentMethod::tryFrom($data['payment_method']) ?? $data['payment_method'],
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            $intents->paidOn($data['paid_at'] ?? null),
            $paidAt,
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Payment recorded.')
            ->with(WorksheetContinuity::SESSION_SCOPE, WorksheetContinuity::declare(
                WorksheetContinuity::SETTLEMENT,
            ));
    }
}
