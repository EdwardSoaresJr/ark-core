<?php

namespace App\Ark\Operations\RepairOrders\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairOrderStatusTransition extends Model
{
    protected $table = 'ro_status_transitions';

    protected $fillable = [
        'from_status_slug',
        'to_status_slug',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(RepairOrderStatusDefinition::class, 'from_status_slug', 'slug');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(RepairOrderStatusDefinition::class, 'to_status_slug', 'slug');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RepairOrderStatusTransitionRole::class, 'transition_id');
    }
}
