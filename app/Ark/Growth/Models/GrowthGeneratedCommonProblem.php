<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthGeneratedCommonProblem extends Model
{
    protected $fillable = [
        'slug',
        'growth_opportunity_id',
        'problem',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'problem' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(GrowthOpportunity::class, 'growth_opportunity_id');
    }
}
