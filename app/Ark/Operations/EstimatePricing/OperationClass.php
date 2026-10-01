<?php

namespace App\Ark\Operations\EstimatePricing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationClass extends Model
{
    protected $fillable = [
        'key',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function laborPolicies(): HasMany
    {
        return $this->hasMany(LaborPolicy::class);
    }
}
