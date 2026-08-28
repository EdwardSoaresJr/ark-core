<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthLocationMetric extends Model
{
    protected $fillable = [
        'metric',
        'report_date',
        'value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'value' => 'integer',
            'metadata' => 'array',
        ];
    }
}
