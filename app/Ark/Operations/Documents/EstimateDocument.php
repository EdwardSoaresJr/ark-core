<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceDocumentGuard;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'legacy_arksms_invoice_id',
    'repair_order_id',
    'document_type',
    'document_number',
    'snapshot_json',
    'snapshot_revisions_json',
    'status',
    'pdf_path',
    'generated_at',
    'issued_at',
    'needs_pdf_refresh',
    'refreshed_at',
    'pdf_refreshed_at',
    'customer_presented_at',
    'created_by',
])]
class EstimateDocument extends Model
{
    private static bool $livingInvoiceRefresh = false;
    protected static function booted(): void
    {
        static::updating(function (EstimateDocument $document): void {
            if (! $document->isIssuedInvoice()) {
                return;
            }

            $dirty = array_keys($document->getDirty());
            $allowed = [
                'status',
                'updated_at',
                'pdf_path',
                'generated_at',
                'needs_pdf_refresh',
                'pdf_refreshed_at',
                'refreshed_at',
                'customer_presented_at',
                'snapshot_revisions_json',
            ];

            if (in_array('snapshot_json', $dirty, true) && $document->isLivingInvoiceRefresh()) {
                $allowed[] = 'snapshot_json';
            }

            if ($dirty !== [] && array_diff($dirty, $allowed) !== []) {
                app(InvoiceDocumentGuard::class)->ensureMutable($document);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'document_type' => FinancialDocumentType::class,
            'snapshot_json' => 'array',
            'snapshot_revisions_json' => 'array',
            'document_number' => 'integer',
            'generated_at' => 'datetime',
            'issued_at' => 'datetime',
            'needs_pdf_refresh' => 'boolean',
            'refreshed_at' => 'datetime',
            'pdf_refreshed_at' => 'datetime',
            'customer_presented_at' => 'datetime',
        ];
    }

    public function isInvoice(): bool
    {
        return $this->document_type === FinancialDocumentType::Invoice
            || $this->document_type?->value === FinancialDocumentType::Invoice->value;
    }

    public function isIssuedInvoice(): bool
    {
        return $this->isInvoice()
            && in_array($this->status, [
                InvoiceStatus::Issued->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Paid->value,
            ], true);
    }

    public function wasPresentedToCustomer(): bool
    {
        return $this->isInvoice() && $this->customer_presented_at !== null;
    }

    public function markPresentedToCustomer(): self
    {
        if (! $this->isInvoice() || $this->customer_presented_at !== null) {
            return $this;
        }

        $this->forceFill(['customer_presented_at' => now()])->save();

        return $this;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function syncLivingSnapshot(array $snapshot): self
    {
        static::$livingInvoiceRefresh = true;

        try {
            $this->forceFill([
                'snapshot_json' => $snapshot,
                'needs_pdf_refresh' => true,
                'refreshed_at' => now(),
            ])->save();
        } finally {
            static::$livingInvoiceRefresh = false;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $details  what triggered the revision and the amounts at that moment
     */
    public function archiveCurrentSnapshotAndReplace(array $snapshot, ?User $actor = null, string $reason = 'approved_work_changed', array $details = []): self
    {
        $revisions = is_array($this->snapshot_revisions_json) ? $this->snapshot_revisions_json : [];
        $prior = is_array($this->snapshot_json) ? $this->snapshot_json : [];
        $revision = $this->currentRevisionNumber();

        $revisions[] = [
            'revision' => $revision,
            'archived_at' => now()->toIso8601String(),
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
            'invoice_total_cents' => InvoiceSnapshotBuilder::invoiceTotalCents($prior),
            'presented_at' => $this->customer_presented_at?->toIso8601String(),
            'pdf_path' => InvoiceRevisionPdf::preserveCurrent($this, $revision),
            ...$details,
            'snapshot' => $prior,
        ];

        static::$livingInvoiceRefresh = true;

        try {
            $this->forceFill([
                'snapshot_revisions_json' => $revisions,
                'snapshot_json' => $snapshot,
                'needs_pdf_refresh' => true,
                'refreshed_at' => now(),
                // New bill has not been shown to the customer yet.
                'customer_presented_at' => null,
                'pdf_path' => null,
            ])->save();
        } finally {
            static::$livingInvoiceRefresh = false;
        }

        return $this;
    }

    /**
     * Versions number from 1, the invoice as first issued. Archived entries written before
     * revisions were numbered take their position.
     */
    public function currentRevisionNumber(): int
    {
        return count(is_array($this->snapshot_revisions_json) ? $this->snapshot_revisions_json : []) + 1;
    }

    /**
     * Earlier versions of this invoice, oldest first, without their snapshots.
     *
     * @return list<array{revision: int, archived_at: string|null, reason: string|null, trigger: string|null, actor_user_id: int|null, invoice_total_cents: int, presented_at: string|null, pdf_path: string|null, collected_cents: int|null}>
     */
    public function priorRevisions(): array
    {
        $revisions = is_array($this->snapshot_revisions_json) ? array_values($this->snapshot_revisions_json) : [];

        return array_map(fn (array $entry, int $index): array => [
            'revision' => (int) ($entry['revision'] ?? $index + 1),
            'archived_at' => $entry['archived_at'] ?? null,
            'reason' => $entry['reason'] ?? null,
            'trigger' => $entry['trigger'] ?? null,
            'actor_user_id' => isset($entry['actor_user_id']) ? (int) $entry['actor_user_id'] : null,
            'invoice_total_cents' => (int) ($entry['invoice_total_cents'] ?? 0),
            'presented_at' => $entry['presented_at'] ?? null,
            'pdf_path' => $entry['pdf_path'] ?? null,
            'collected_cents' => isset($entry['collected_cents']) ? (int) $entry['collected_cents'] : null,
        ], $revisions, array_keys($revisions));
    }

    private function isLivingInvoiceRefresh(): bool
    {
        return static::$livingInvoiceRefresh;
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
