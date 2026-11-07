<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
    /**
     * Payment method of a store credit issuance that turns credit due on a revised invoice into
     * store credit. Unlike credit issued from an overpayment at the counter, that money is already
     * counted in the invoice's payments, so it settles the invoice's credit due.
     */
    public const CREDIT_DUE_STORE_CREDIT = 'credit_due';

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'payment_method' => PaymentMethodCast::class,
            'amount_cents' => 'integer',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function newEloquentBuilder($query): RepairOrderLedgerEntryQuery
    {
        return new RepairOrderLedgerEntryQuery($query);
    }

    /**
     * @return Attribute<PaymentMethod|string|null, PaymentMethod|string|null>
     */
    protected function method(): Attribute
    {
        return Attribute::make(
            get: fn (): mixed => $this->payment_method,
            set: fn (mixed $value): array => ['payment_method' => $value],
        );
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
