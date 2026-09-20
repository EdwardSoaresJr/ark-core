<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTemplateLine extends Model
{
    protected $fillable = [
        'work_template_id',
        'type',
        'description',
        'quantity',
        'unit_price_cents',
        'part_cost_cents',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => RepairOrderLineType::class,
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'part_cost_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkTemplate::class, 'work_template_id');
    }
}
