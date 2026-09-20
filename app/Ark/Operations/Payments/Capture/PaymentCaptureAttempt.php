<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCaptureAttempt extends Model
{
    protected $fillable = [
        'public_id',
        'repair_order_id',
        'customer_id',
        'amount_cents',
        'currency',
        'context_kind',
        'capture_method',
        'status',
        'idempotency_key',
        'device_ref',
        'cloud_capture_id',
        'provider',
        'provider_payment_id',
        'provider_refs',
        'failure_reason',
        'ledger_entry_id',
        'initiated_by',
        'initiated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'context_kind' => PaymentCaptureContextKind::class,
            'capture_method' => PaymentCaptureMethod::class,
            'status' => PaymentCaptureAttemptStatus::class,
            'provider_refs' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLedgerEntry::class, 'ledger_entry_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function reference(): string
    {
        return 'ark-pay-'.$this->public_id;
    }

    public function hasLedgerEntry(): bool
    {
        return $this->ledger_entry_id !== null;
    }
}
