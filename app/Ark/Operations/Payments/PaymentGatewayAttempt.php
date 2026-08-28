<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_order_id',
    'customer_id',
    'financial_document_id',
    'gateway',
    'capture_surface',
    'amount_cents',
    'currency',
    'idempotency_key',
    'square_payment_id',
    'square_checkout_id',
    'status',
    'failure_reason',
    'processing_fee_cents',
    'ledger_entry_id',
    'initiated_by',
    'customer_access_token_id',
    'estimate_access_token_id',
    'initiated_at',
    'completed_at',
])]
class PaymentGatewayAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'capture_surface' => PaymentCaptureSurface::class,
            'status' => PaymentGatewayAttemptStatus::class,
            'amount_cents' => 'integer',
            'processing_fee_cents' => 'integer',
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

    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(EstimateDocument::class, 'financial_document_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLedgerEntry::class, 'ledger_entry_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function customerAccessToken(): BelongsTo
    {
        return $this->belongsTo(CustomerDocumentAccessToken::class, 'customer_access_token_id');
    }

    public function estimateAccessToken(): BelongsTo
    {
        return $this->belongsTo(EstimateAccessToken::class, 'estimate_access_token_id');
    }

    public function referenceId(): string
    {
        return 'ark-pay-'.$this->id;
    }

    public function collectsDeposit(): bool
    {
        if (in_array($this->capture_surface, [
            PaymentCaptureSurface::PortalEstimateDeposit,
            PaymentCaptureSurface::PortalDepositRequest,
        ], true)) {
            return true;
        }

        return $this->financial_document_id === null
            && in_array($this->capture_surface, [
                PaymentCaptureSurface::Terminal,
                PaymentCaptureSurface::Keyed,
            ], true);
    }
}
