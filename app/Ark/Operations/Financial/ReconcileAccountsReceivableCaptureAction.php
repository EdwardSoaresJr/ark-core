<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentPaidAt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Retries ledger allocation for a processor-settled AR capture without charging again.
 */
final class ReconcileAccountsReceivableCaptureAction
{
    public function __construct(
        private readonly AllocateCustomerPaymentAction $allocateCustomerPayment,
    ) {}

    public function execute(PaymentCaptureAttempt $attempt, ?User $actor = null): PaymentCaptureAttempt
    {
        if ($attempt->context_kind !== PaymentCaptureContextKind::AccountsReceivable) {
            throw ValidationException::withMessages([
                'capture' => 'Only Accounts Receivable card captures can be allocation-reconciled here.',
            ]);
        }

        return DB::transaction(function () use ($attempt, $actor): PaymentCaptureAttempt {
            /** @var PaymentCaptureAttempt $locked */
            $locked = PaymentCaptureAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->customer_payment_receipt_id !== null) {
                $locked->status = PaymentCaptureAttemptStatus::Succeeded;
                $locked->completed_at ??= now();
                $locked->failure_reason = null;
                $locked->save();

                return $locked->fresh();
            }

            if ($locked->status !== PaymentCaptureAttemptStatus::ReconciliationRequired
                && trim((string) $locked->provider_payment_id) === '') {
                throw ValidationException::withMessages([
                    'capture' => 'This capture is not waiting for allocation reconciliation.',
                ]);
            }

            if (trim((string) $locked->provider_payment_id) === '') {
                throw ValidationException::withMessages([
                    'capture' => 'Cannot allocate without a confirmed processor payment id.',
                ]);
            }

            try {
                $receipt = $this->allocate($locked, $actor);
            } catch (Throwable $exception) {
                $locked->status = PaymentCaptureAttemptStatus::ReconciliationRequired;
                $locked->failure_reason = 'allocation_failed';
                $locked->completed_at = null;
                $locked->save();

                throw new RuntimeException(
                    'Allocation still failed after the card was charged. Capture '.$locked->public_id.' remains in reconciliation. '.$exception->getMessage(),
                    previous: $exception,
                );
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

            return $locked->fresh();
        });
    }

    private function allocate(PaymentCaptureAttempt $attempt, ?User $actor): CustomerPaymentReceipt
    {
        $plan = is_array($attempt->allocation_plan) ? $attempt->allocation_plan : [];
        $allocations = is_array($plan['allocations'] ?? null) ? $plan['allocations'] : [];
        if ($allocations === []) {
            throw new RuntimeException('Accounts receivable card capture is missing an allocation plan.');
        }

        $disposition = OverpaymentDisposition::tryFrom((string) ($plan['disposition'] ?? ''))
            ?? OverpaymentDisposition::KeepCredit;

        $customer = Customer::query()->findOrFail($attempt->customer_id);
        $intentKey = is_string($plan['intent_key'] ?? null) && $plan['intent_key'] !== ''
            ? (string) $plan['intent_key']
            : (string) Str::uuid();
        $reference = filled($plan['reference'] ?? null)
            ? (string) $plan['reference']
            : 'capture:'.$attempt->public_id;

        return $this->allocateCustomerPayment->execute(
            $customer,
            (int) $attempt->amount_cents,
            PaymentMethod::Card,
            $allocations,
            $intentKey,
            $actor ?? ($attempt->initiated_by ? User::query()->find($attempt->initiated_by) : null),
            $reference,
            RepairOrderPaymentPaidAt::fromDateInput(
                is_string($attempt->paid_on) ? $attempt->paid_on : null,
            ),
            $disposition,
        );
    }
}
