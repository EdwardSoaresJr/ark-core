<?php

namespace App\Ark\Operations\EstimatePricing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborPolicy extends Model
{
    protected $fillable = [
        'billing_posture',
        'operation_class_id',
        'rate_type',
        'hourly_rate_cents',
        'effective_from',
        'effective_until',
        'priority',
        'version',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => LaborRateType::class,
            'hourly_rate_cents' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'priority' => 'integer',
            'version' => 'integer',
        ];
    }

    public function operationClass(): BelongsTo
    {
        return $this->belongsTo(OperationClass::class);
    }
}
