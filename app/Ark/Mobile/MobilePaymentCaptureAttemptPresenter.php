<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\RepairOrders\RepairOrder;

final class MobilePaymentCaptureAttemptPresenter
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAttempt(PaymentCaptureAttempt $attempt): array
    {
        $repairOrder = $attempt->relationLoaded('repairOrder')
            ? $attempt->repairOrder
            : RepairOrder::query()->find($attempt->repair_order_id);
        $totals = $repairOrder !== null
            ? $this->totalsCalculator->totalsFor($repairOrder)
            : null;

        return [
            'id' => $attempt->id,
            'public_id' => $attempt->public_id,
            'status' => $attempt->status->value,
            'amount_cents' => $attempt->amount_cents,
            'amount' => $totals?->format($attempt->amount_cents) ?? number_format($attempt->amount_cents / 100, 2, '.', ''),
            'capture_method' => $attempt->capture_method->value,
            'context_kind' => $attempt->context_kind->value,
            'device_ref' => $attempt->device_ref,
            'failure_reason' => $attempt->failure_reason,
            'ledger_entry_id' => $attempt->ledger_entry_id,
        ];
    }
}
