<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * RO payment history on mobile — read-only ledger rows with void affordances
 * when closeout authority allows (same types as desktop financial rail).
 */
final class MobileLedgerProjection
{
    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function control(RepairOrder $repairOrder, User $viewer, string $profile): ?array
    {
        if ($profile === 'technician'
            || ! $viewer->can(ArkCapability::RepairOrdersManage->value)) {
            return null;
        }

        if ($repairOrder->isTerminal()) {
            return null;
        }

        $entries = RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->active()
            ->with('recordedBy:id,name')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $totals = $this->totalsCalculator->totalsFor($repairOrder);
        $balance = $repairOrder->balanceDue();
        $canManage = $this->access->canManageLedgerEntries($viewer, $repairOrder);

        return [
            'can_manage' => $canManage,
            'balance_due_label' => $balance->hasIssuedInvoice
                ? $totals->format($balance->balanceDueCents)
                : null,
            'entries' => $entries
                ->map(fn (RepairOrderLedgerEntry $entry): array => [
                    'id' => $entry->id,
                    'type' => $entry->entry_type->value,
                    'type_label' => $this->typeLabel($entry->entry_type),
                    'method_label' => $entry->payment_method?->label(),
                    'amount_label' => $totals->format($entry->amount_cents),
                    'reference' => filled($entry->reference) ? (string) $entry->reference : null,
                    'recorded_at_label' => $entry->recorded_at?->timezone(config('app.display_timezone'))->format('M j, g:i A'),
                    'recorded_by' => $entry->recordedBy?->name,
                    'can_void' => $canManage && $this->access->canVoidLedgerEntry($viewer, $repairOrder, $entry),
                ])
                ->values()
                ->all(),
        ];
    }

    private function typeLabel(LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::Deposit => 'Deposit',
            LedgerEntryType::Payment => 'Payment',
            LedgerEntryType::Refund => 'Refund',
            LedgerEntryType::Adjustment => 'Adjustment',
            LedgerEntryType::WriteOff => 'Write-off',
            LedgerEntryType::StoreCreditIssuance => 'Store credit issued',
            LedgerEntryType::StoreCreditApplication => 'Store credit applied',
        };
    }
}
