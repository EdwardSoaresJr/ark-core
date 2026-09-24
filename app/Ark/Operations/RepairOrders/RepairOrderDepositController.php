<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\NotifyRepairOrderFinancialChange;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RepairOrderDepositController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLedgerDepositRecorder $deposits,
        EstimateTotalsCalculator $totalsCalculator,
        RepairOrderDepositRecordingGuard $depositGuard,
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
            'reference' => ['nullable', 'string', 'max:255'],
            'deposit_confirmed' => ['accepted'],
        ], [
            'deposit_confirmed.accepted' => 'Confirm that the customer paid before recording this deposit.',
        ]);

        $amountCents = $totalsCalculator->unitPriceCents($data['amount']);
        $depositGuard->validateAmount($repairOrder, $amountCents);

        $deposits->record(
            $repairOrder,
            $amountCents,
            PaymentMethod::from($data['payment_method']),
            $request->user(),
            filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
        );

        $actor = $request->user();

        DB::afterCommit(function () use ($repairOrder, $actor): void {
            app(NotifyRepairOrderFinancialChange::class)->notify(
                $repairOrder,
                reason: 'deposit_recorded',
                actor: $actor,
            );
        });

        return redirect()
            ->back()
            ->with('status', 'Deposit recorded.');
    }
}
