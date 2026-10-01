<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'intent_key',
    'repair_order_id',
    'operation',
    'payload_fingerprint',
    'ledger_entry_id',
])]
class FinancialSubmissionIntent extends Model
{
    protected function casts(): array
    {
        return [
            'operation' => FinancialSubmissionOperation::class,
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLedgerEntry::class, 'ledger_entry_id');
    }
}
