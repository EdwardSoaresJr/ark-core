<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthCoreWebVital extends Model
{
    protected $fillable = [
        'url',
        'period_start',
        'period_end',
        'lcp_ms',
        'inp_ms',
        'cls',
        'lcp_rating',
        'inp_rating',
        'cls_rating',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'lcp_ms' => 'decimal:2',
            'inp_ms' => 'decimal:2',
            'cls' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
