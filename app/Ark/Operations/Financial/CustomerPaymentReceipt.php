<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'intent_key',
    'payload_fingerprint',
    'amount_cents',
    'allocated_cents',
    'unapplied_cents',
    'change_cents',
    'refund_pending_cents',
    'refund_evidence_kind',
    'refund_evidence_reference',
    'refund_confirmed_at',
    'refund_confirmed_by',
    'payment_method',
    'reference',
    'notes',
    'recorded_at',
    'recorded_by',
    'voided_at',
    'voided_by',
])]
class CustomerPaymentReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethodCast::class,
            'amount_cents' => 'integer',
            'allocated_cents' => 'integer',
            'unapplied_cents' => 'integer',
            'change_cents' => 'integer',
            'refund_pending_cents' => 'integer',
            'refund_confirmed_at' => 'datetime',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function balancedAmountCents(): int
    {
        return (int) $this->allocated_cents
            + (int) $this->unapplied_cents
            + (int) $this->change_cents
            + (int) $this->refund_pending_cents;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(RepairOrderLedgerEntry::class, 'customer_payment_receipt_id');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function requiresProcessorRefundConfirmation(): bool
    {
        return app(ConfirmCustomerPaymentRefundAction::class)->requiresProcessorConfirmation($this);
    }

    public function allowsManualRefundConfirmation(): bool
    {
        return app(ConfirmCustomerPaymentRefundAction::class)->allowsManualReturnConfirmation($this);
    }
}
