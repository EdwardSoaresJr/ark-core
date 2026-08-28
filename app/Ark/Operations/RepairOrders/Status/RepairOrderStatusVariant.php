<?php

namespace App\Ark\Operations\RepairOrders\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderStatusVariant extends Model
{
    protected $table = 'ro_status_variants';

    protected $fillable = [
        'status_slug',
        'variant_key',
        'name',
        'affects_metrics',
        'bypass_standard_close_rules',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'affects_metrics' => 'boolean',
            'bypass_standard_close_rules' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RepairOrderStatusDefinition::class, 'status_slug', 'slug');
    }
}
