<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a verified Cloud capture result to Core financial authority - once.
 */
final class ApplyPaymentCaptureResultAction
{
    public function __construct(
        private readonly RecordLedgerEntryAction $ledger,
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public function apply(PaymentCaptureAttempt $attempt, array $result): PaymentCaptureAttempt
    {
        return DB::transaction(function () use ($attempt, $result) {
            /** @var PaymentCaptureAttempt $locked */
            $locked = PaymentCaptureAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $status = PaymentCaptureAttemptStatus::tryFrom((string) ($result['status'] ?? ''))
                ?? $locked->status;

            $locked->cloud_capture_id = (string) ($result['capture_id'] ?? $locked->cloud_capture_id);
            $locked->provider = (string) ($result['provider'] ?? $locked->provider ?? 'platform');
            $locked->provider_payment_id = isset($result['provider_payment_id'])
                ? (string) $result['provider_payment_id']
                : $locked->provider_payment_id;
            if (isset($result['provider_refs']) && is_array($result['provider_refs'])) {
                $locked->provider_refs = $result['provider_refs'];
            }
            if (isset($result['reason_code']) && $status !== PaymentCaptureAttemptStatus::Succeeded) {
                $locked->failure_reason = (string) $result['reason_code'];
            }

            if ($locked->hasLedgerEntry()) {
                $locked->status = PaymentCaptureAttemptStatus::Succeeded;
                $locked->completed_at ??= now();
                $locked->save();

                return $locked->fresh();
            }

            if ($status === PaymentCaptureAttemptStatus::Succeeded) {
                $entryId = $this->recordMoney($locked);
                $locked->ledger_entry_id = $entryId;
                $locked->status = PaymentCaptureAttemptStatus::Succeeded;
                $locked->completed_at = now();
                $locked->failure_reason = null;
                $locked->save();

                return $locked->fresh(['ledgerEntry']);
            }

            $locked->status = $status;
            if (in_array($status, [
                PaymentCaptureAttemptStatus::Failed,
                PaymentCaptureAttemptStatus::Cancelled,
            ], true)) {
                $locked->completed_at = now();
            }
            $locked->save();

            return $locked->fresh();
        });
    }

    private function recordMoney(PaymentCaptureAttempt $attempt): int
    {
        $repairOrder = RepairOrder::query()->findOrFail($attempt->repair_order_id);
        $repairOrder->ensureOpenForEditing();
        $actor = $attempt->initiated_by
            ? User::query()->find($attempt->initiated_by)
            : null;
        $reference = 'capture:'.$attempt->public_id;

        if ($attempt->context_kind === PaymentCaptureContextKind::Deposit) {
            $entry = $this->ledger->recordDeposit(
                $repairOrder,
                $attempt->amount_cents,
                PaymentMethod::Card,
                $actor,
                $reference,
            );

            return $entry->id;
        }

        $entries = $this->ledger->recordPayment(
            $repairOrder,
            $attempt->amount_cents,
            PaymentMethod::Card,
            $actor,
            $reference,
        );

        return $entries[0]->id;
    }

    public function balanceAfter(RepairOrder $repairOrder): int
    {
        return $this->balanceDue->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    }
}
