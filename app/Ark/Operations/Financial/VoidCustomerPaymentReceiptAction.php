<?php

namespace App\Ark\Operations\Financial;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VoidCustomerPaymentReceiptAction
{
    public function __construct(
        private readonly VoidLedgerEntryAction $voidLedger,
    ) {}

    public function execute(CustomerPaymentReceipt $receipt, ?User $actor = null): CustomerPaymentReceipt
    {
        if ($receipt->isVoided()) {
            throw new RuntimeException('This payment receipt is already voided.');
        }

        return DB::transaction(function () use ($receipt, $actor): CustomerPaymentReceipt {
            /** @var CustomerPaymentReceipt $locked */
            $locked = CustomerPaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new RuntimeException('This payment receipt is already voided.');
            }

            $entries = RepairOrderLedgerEntry::query()
                ->where('customer_payment_receipt_id', $locked->id)
                ->whereNull('voided_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $this->voidLedger->execute($entry, $actor);
            }

            $locked->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor?->id,
            ])->save();

            return $locked->refresh();
        });
    }
}
