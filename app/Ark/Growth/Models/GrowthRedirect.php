<?php

namespace App\Ark\Growth\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthRedirect extends Model
{
    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
        'is_wildcard',
        'is_active',
        'hit_count',
        'last_hit_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_wildcard' => 'boolean',
            'is_active' => 'boolean',
            'hit_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function isGone(): bool
    {
        return $this->status_code === 410;
    }
}
