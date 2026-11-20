<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Confirms a pending manual refund/return with auditable staff evidence.
 *
 * Cash/check (and similar manual tenders) may be confirmed when staff record a
 * typed proof of the physical return. Processor-card refunds stay pending until
 * a verified processor settlement path exists — a typed attestation is not enough.
 */
final class ConfirmCustomerPaymentRefundAction
{
    public const EVIDENCE_MANUAL_RETURN = 'manual_return';

    public function execute(
        CustomerPaymentReceipt $receipt,
        string $evidenceReference,
        ?User $actor = null,
        string $evidenceKind = self::EVIDENCE_MANUAL_RETURN,
    ): CustomerPaymentReceipt {
        $evidenceReference = trim($evidenceReference);
        $evidenceKind = trim($evidenceKind);

        if ($evidenceReference === '') {
            throw ValidationException::withMessages([
                'evidence' => 'Record a typed proof of the return (check number, cash till note, courier receipt).',
            ]);
        }

        if ($evidenceKind !== self::EVIDENCE_MANUAL_RETURN) {
            throw ValidationException::withMessages([
                'evidence_kind' => 'Only a manual return confirmation is available here. Card refunds require processor confirmation.',
            ]);
        }

        return DB::transaction(function () use ($receipt, $evidenceReference, $evidenceKind, $actor): CustomerPaymentReceipt {
            /** @var CustomerPaymentReceipt $locked */
            $locked = CustomerPaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Cannot confirm a refund on a voided payment receipt.',
                ]);
            }

            $pending = (int) $locked->refund_pending_cents;
            if ($pending <= 0) {
                throw ValidationException::withMessages([
                    'receipt' => 'This payment has no pending refund/return to confirm.',
                ]);
            }

            if ($this->requiresProcessorConfirmation($locked)) {
                throw ValidationException::withMessages([
                    'receipt' => 'This pending amount is tied to a card/processor payment. '
                        .'A manually entered reference cannot mark the refund complete. '
                        .'Wait for verified processor refund settlement.',
                ]);
            }

            if (! $this->allowsManualReturnConfirmation($locked)) {
                throw ValidationException::withMessages([
                    'receipt' => 'Manual refund confirmation is only for cash, check, or other non-card tenders.',
                ]);
            }

            $locked->forceFill([
                'refund_pending_cents' => 0,
                'change_cents' => (int) $locked->change_cents + $pending,
                'refund_evidence_kind' => $evidenceKind,
                'refund_evidence_reference' => $evidenceReference,
                'refund_confirmed_at' => now(),
                'refund_confirmed_by' => $actor?->id,
                'notes' => trim(($locked->notes ? $locked->notes.' ' : '')
                    .'Manual return of $'.number_format($pending / 100, 2)
                    .' confirmed with proof "'.$evidenceReference.'"'
                    .($actor?->name ? ' by '.$actor->name : '')
                    .' at '.now()->toDateTimeString().'.'),
            ])->save();

            if ($locked->amount_cents !== $locked->balancedAmountCents()) {
                throw new RuntimeException('Confirmed refund receipt amounts do not balance.');
            }

            return $locked->fresh();
        });
    }

    public function requiresProcessorConfirmation(CustomerPaymentReceipt $receipt): bool
    {
        if (PaymentMethod::stored($receipt->payment_method) === PaymentMethod::Card->value) {
            return true;
        }

        return PaymentCaptureAttempt::query()
            ->where('customer_payment_receipt_id', $receipt->id)
            ->exists();
    }

    public function allowsManualReturnConfirmation(CustomerPaymentReceipt $receipt): bool
    {
        if ($this->requiresProcessorConfirmation($receipt)) {
            return false;
        }

        $method = PaymentMethod::stored($receipt->payment_method);

        return in_array($method, [
            PaymentMethod::Cash->value,
            PaymentMethod::Check->value,
            PaymentMethod::Eft->value,
            PaymentMethod::Financing->value,
        ], true);
    }
}
