<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'repair_order_id',
    'financial_document_id',
    'token_hash',
    'scope',
    'amount_cents',
    'expires_at',
    'last_used_at',
    'revoked_at',
])]
class CustomerDocumentAccessToken extends Model
{
    public const SCOPE_PAY_INVOICE = 'pay_invoice';

    public const SCOPE_PAY_DEPOSIT = 'pay_deposit';

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(EstimateDocument::class, 'financial_document_id');
    }

    public function isUsable(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->revoked_at === null
            && $this->expires_at->greaterThan($now);
    }

    public function isDepositRequest(): bool
    {
        return $this->scope === self::SCOPE_PAY_DEPOSIT;
    }

    public function isInvoicePay(): bool
    {
        return $this->scope === self::SCOPE_PAY_INVOICE;
    }
}
