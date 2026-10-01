<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealerQuoteLine extends Model
{
    protected $fillable = [
        'dealer_quote_id',
        'position',
        'quantity',
        'part_number',
        'description',
        'unit_cost_cents',
        'extended_cost_cents',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:2',
            'unit_cost_cents' => 'integer',
            'extended_cost_cents' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(DealerQuote::class, 'dealer_quote_id');
    }

    public function estimateLines(): HasMany
    {
        return $this->hasMany(RepairOrderLine::class, 'dealer_quote_line_id');
    }

    public function unitCostDecimal(): string
    {
        return number_format($this->unit_cost_cents / 100, 2, '.', '');
    }

    public function sourceKey(): string
    {
        return 'dq:'.$this->id;
    }
}
