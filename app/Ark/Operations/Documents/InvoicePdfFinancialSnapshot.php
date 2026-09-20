<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use Brick\Money\Money;

final class InvoicePdfFinancialSnapshot
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function append(EstimateDocument $document, array $snapshot): array
    {
        if (! $document->isInvoice()) {
            return $snapshot;
        }

        $document->loadMissing('repairOrder');

        $repairOrder = $document->repairOrder;

        if ($repairOrder === null) {
            return $snapshot;
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if (! $balance->hasIssuedInvoice) {
            return $snapshot;
        }

        $entries = RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->active()
            ->whereIn('entry_type', [
                LedgerEntryType::Deposit,
                LedgerEntryType::Payment,
                LedgerEntryType::Refund,
                LedgerEntryType::Adjustment,
                LedgerEntryType::WriteOff,
                LedgerEntryType::StoreCreditApplication,
            ])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (RepairOrderLedgerEntry $entry): array => [
                'type_label' => $this->typeLabel($entry->entry_type),
                'method_label' => $entry->payment_method?->label(),
                'amount' => $this->formatCents($entry->amount_cents),
                'amount_cents' => $entry->amount_cents,
                'reduces_balance' => $entry->entry_type->reducesBalanceDue(),
                'recorded_at_display' => $entry->recorded_at?->timezone(config('app.display_timezone'))->format('M j, Y'),
                'reference' => filled($entry->reference) ? (string) $entry->reference : null,
            ])
            ->values()
            ->all();

        $snapshot['financial'] = [
            'deposits_applied' => $this->formatCents($balance->depositsAppliedCents),
            'deposits_applied_cents' => $balance->depositsAppliedCents,
            'payments_applied' => $this->formatCents($balance->paymentsAppliedCents),
            'payments_applied_cents' => $balance->paymentsAppliedCents,
            'credits_applied' => $this->formatCents($balance->creditsAppliedCents),
            'credits_applied_cents' => $balance->creditsAppliedCents,
            'adjustments' => $this->formatCents($balance->adjustmentsCents),
            'adjustments_cents' => $balance->adjustmentsCents,
            'write_offs' => $this->formatCents($balance->writeOffsCents),
            'write_offs_cents' => $balance->writeOffsCents,
            'balance_due' => $this->formatCents($balance->balanceDueCents),
            'balance_due_cents' => $balance->balanceDueCents,
            'collection_disposition' => RepairOrderCollectionDisposition::tryFromMixed($repairOrder->collection_disposition)->value,
            'collection_waiver_label' => RepairOrderCollectionDisposition::tryFromMixed($repairOrder->collection_disposition)->waiverCustomerLabel(),
            'entries' => $entries,
        ];

        return $snapshot;
    }

    private function typeLabel(LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::Deposit => 'Deposit',
            LedgerEntryType::Payment => 'Payment',
            LedgerEntryType::Refund => 'Refund',
            LedgerEntryType::Adjustment => 'Adjustment',
            LedgerEntryType::WriteOff => 'Write-off',
            LedgerEntryType::StoreCreditApplication => 'Store credit applied',
        };
    }

    private function formatCents(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')
            ->getAmount()
            ->toScale(2)
            ->__toString();
    }
}
