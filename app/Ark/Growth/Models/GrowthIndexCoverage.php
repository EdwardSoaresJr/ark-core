<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthIndexCoverage extends Model
{
    protected $table = 'growth_index_coverage';

    protected $fillable = [
        'url',
        'status',
        'last_crawl_at',
        'detail',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_crawl_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
