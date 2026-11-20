<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\AllocateCustomerPaymentAction;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\CustomerPaymentReceipt;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\OverpaymentDisposition;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentPaidAt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Applies a verified Cloud capture result to Core financial authority - once.
 *
 * For Accounts Receivable captures: processor settlement is persisted even when
 * invoice allocation fails, leaving ReconciliationRequired so staff can retry
 * allocation without charging the card again.
 */
final class ApplyPaymentCaptureResultAction
{
    public function __construct(
        private readonly RecordLedgerEntryAction $ledger,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly AllocateCustomerPaymentAction $allocateCustomerPayment,
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

            if ($locked->hasLedgerEntry() || $locked->customer_payment_receipt_id !== null) {
                $locked->status = PaymentCaptureAttemptStatus::Succeeded;
                $locked->completed_at ??= now();
                $locked->save();

                return $locked->fresh();
            }

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

            if ($status === PaymentCaptureAttemptStatus::Succeeded && ! $this->confirmedProviderSettlement($locked)) {
                $locked->status = PaymentCaptureAttemptStatus::Failed;
                $locked->failure_reason = 'unconfirmed_capture';
                $locked->completed_at = now();
                $locked->save();

                return $locked->fresh();
            }

            if ($status === PaymentCaptureAttemptStatus::Succeeded && $this->reportedAmountDisagrees($locked, $result)) {
                $locked->status = PaymentCaptureAttemptStatus::ReconciliationRequired;
                $locked->completed_at = null;
                $locked->save();

                return $locked->fresh();
            }

            if ($status === PaymentCaptureAttemptStatus::Succeeded) {
                if ($locked->context_kind === PaymentCaptureContextKind::AccountsReceivable) {
                    return $this->completeAccountsReceivableCapture($locked);
                }

                $locked->ledger_entry_id = $this->recordMoney($locked);
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

    private function completeAccountsReceivableCapture(PaymentCaptureAttempt $locked): PaymentCaptureAttempt
    {
        // Persist processor success before allocation so a ledger failure cannot erase the charge.
        $locked->status = PaymentCaptureAttemptStatus::ReconciliationRequired;
        $locked->failure_reason = 'allocation_pending';
        $locked->completed_at = null;
        $locked->save();

        try {
            $receipt = $this->recordAccountsReceivableAllocation($locked);
        } catch (Throwable $exception) {
            $locked->status = PaymentCaptureAttemptStatus::ReconciliationRequired;
            $locked->failure_reason = 'allocation_failed';
            $locked->completed_at = null;
            $locked->save();

            return $locked->fresh();
        }

        $locked->customer_payment_receipt_id = $receipt->id;
        $locked->ledger_entry_id = $receipt->ledgerEntries()
            ->where('entry_type', LedgerEntryType::Payment)
            ->orderBy('id')
            ->value('id');
        $locked->status = PaymentCaptureAttemptStatus::Succeeded;
        $locked->completed_at = now();
        $locked->failure_reason = null;
        $locked->save();

        return $locked->fresh(['ledgerEntry']);
    }

    /**
     * A card ledger entry requires a provider payment id from a processor that can settle.
     * The local recorder and a bare success status do not qualify. Existing ledger rows are left alone.
     */
    private function confirmedProviderSettlement(PaymentCaptureAttempt $attempt): bool
    {
        $provider = strtolower(trim((string) $attempt->provider));
        $paymentId = trim((string) $attempt->provider_payment_id);

        if ($provider === '' || $provider === 'record' || $paymentId === '') {
            return false;
        }

        return preg_match('/^capture-\d+$/', $paymentId) !== 1;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function reportedAmountDisagrees(PaymentCaptureAttempt $attempt, array $result): bool
    {
        if (! array_key_exists('amount_cents', $result) || $result['amount_cents'] === null) {
            return false;
        }

        return (int) $result['amount_cents'] !== $attempt->amount_cents;
    }

    private function recordMoney(PaymentCaptureAttempt $attempt): int
    {
        $repairOrder = RepairOrder::query()->findOrFail($attempt->repair_order_id);
        // Only a capture still in flight may finish after its order closes. The order stays closed.
        if (! $attempt->status->isOpen()) {
            $repairOrder->ensureOpenForEditing();
        }
        $actor = $attempt->initiated_by
            ? User::query()->find($attempt->initiated_by)
            : null;
        $reference = 'capture:'.$attempt->public_id;
        $recordedAt = RepairOrderPaymentPaidAt::fromDateInput(
            is_string($attempt->paid_on) ? $attempt->paid_on : null,
        );

        if ($attempt->context_kind === PaymentCaptureContextKind::Deposit) {
            $entry = $this->ledger->recordDeposit(
                $repairOrder,
                $attempt->amount_cents,
                PaymentMethod::Card,
                $actor,
                $reference,
                $recordedAt,
            );

            return $entry->id;
        }

        $entries = $this->ledger->recordPayment(
            $repairOrder,
            $attempt->amount_cents,
            PaymentMethod::Card,
            $actor,
            $reference,
            $recordedAt,
        );

        return $entries[0]->id;
    }

    private function recordAccountsReceivableAllocation(PaymentCaptureAttempt $attempt): CustomerPaymentReceipt
    {
        $plan = is_array($attempt->allocation_plan) ? $attempt->allocation_plan : [];
        $allocations = is_array($plan['allocations'] ?? null) ? $plan['allocations'] : [];
        if ($allocations === []) {
            throw new RuntimeException('Accounts receivable card capture is missing an allocation plan.');
        }

        $customer = Customer::query()->findOrFail($attempt->customer_id);
        $actor = $attempt->initiated_by
            ? User::query()->find($attempt->initiated_by)
            : null;
        $intentKey = is_string($plan['intent_key'] ?? null) && $plan['intent_key'] !== ''
            ? (string) $plan['intent_key']
            : (string) Str::uuid();
        $reference = filled($plan['reference'] ?? null)
            ? (string) $plan['reference']
            : 'capture:'.$attempt->public_id;
        $disposition = OverpaymentDisposition::tryFrom((string) ($plan['disposition'] ?? ''))
            ?? OverpaymentDisposition::KeepCredit;
        $recordedAt = RepairOrderPaymentPaidAt::fromDateInput(
            is_string($attempt->paid_on) ? $attempt->paid_on : null,
        );

        return $this->allocateCustomerPayment->execute(
            $customer,
            (int) $attempt->amount_cents,
            PaymentMethod::Card,
            $allocations,
            $intentKey,
            $actor,
            $reference,
            $recordedAt,
            $disposition,
        );
    }

    public function balanceAfter(RepairOrder $repairOrder): int
    {
        return $this->balanceDue->forRepairOrder($repairOrder->fresh())->balanceDueCents;
    }
}
