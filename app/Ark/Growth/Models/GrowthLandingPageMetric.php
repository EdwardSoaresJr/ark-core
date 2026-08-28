<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthLandingPageMetric extends Model
{
    protected $fillable = [
        'path',
        'report_date',
        'period_start',
        'period_end',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'page_views',
        'appointments',
        'revenue_cents',
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
            'page_views' => 'integer',
            'appointments' => 'integer',
            'revenue_cents' => 'integer',
            'metadata' => 'array',
        ];
    }
}
