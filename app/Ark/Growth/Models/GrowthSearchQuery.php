<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthSearchQuery extends Model
{
    protected $fillable = [
        'query',
        'report_date',
        'period_start',
        'period_end',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'revenue_cents',
        'repair_order_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'report_date' => 'date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'decimal:4',
            'position' => 'decimal:2',
            'revenue_cents' => 'integer',
            'repair_order_count' => 'integer',
            'metadata' => 'array',
        ];
    }
}
