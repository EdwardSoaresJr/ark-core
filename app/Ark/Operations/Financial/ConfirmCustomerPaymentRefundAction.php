<?php

namespace App\Ark\Operations\Financial;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Confirms a previously pending excess refund/return with authoritative evidence.
 * Moves refund_pending_cents into change_cents only after staff records proof —
 * never invents a processor refund.
 */
final class ConfirmCustomerPaymentRefundAction
{
    public function execute(
        CustomerPaymentReceipt $receipt,
        string $evidenceReference,
        ?User $actor = null,
    ): CustomerPaymentReceipt {
        $evidenceReference = trim($evidenceReference);
        if ($evidenceReference === '') {
            throw ValidationException::withMessages([
                'evidence' => 'Record a confirmation reference (processor refund id, check number, or staff attestation).',
            ]);
        }

        return DB::transaction(function () use ($receipt, $evidenceReference, $actor): CustomerPaymentReceipt {
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

            $locked->forceFill([
                'refund_pending_cents' => 0,
                'change_cents' => (int) $locked->change_cents + $pending,
                'notes' => trim(($locked->notes ? $locked->notes.' ' : '')
                    .'Pending refund of $'.number_format($pending / 100, 2)
                    .' confirmed ('.$evidenceReference.')'
                    .($actor?->name ? ' by '.$actor->name : '').'.'),
                'reference' => filled($locked->reference)
                    ? $locked->reference
                    : 'refund:'.$evidenceReference,
            ])->save();

            if ($locked->amount_cents !== $locked->balancedAmountCents()) {
                throw new RuntimeException('Confirmed refund receipt amounts do not balance.');
            }

            return $locked->fresh();
        });
    }
}
