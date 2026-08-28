<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_order_id',
    'customer_id',
    'financial_document_id',
    'entry_type',
    'payment_method',
    'amount_cents',
    'reference',
    'notes',
    'recorded_at',
    'voided_at',
    'voided_by',
    'recorded_by',
])]
class RepairOrderLedgerEntry extends Model
{
    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'payment_method' => PaymentMethod::class,
            'amount_cents' => 'integer',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(EstimateDocument::class, 'financial_document_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
