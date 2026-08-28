<?php

namespace App\Ark\Growth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthContent extends Model
{
    protected $fillable = [
        'slug',
        'template',
        'title',
        'path',
        'author_user_id',
        'published_at',
        'indexable',
        'priority',
        'last_crawl_at',
        'search_clicks',
        'search_impressions',
        'revenue_cents',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'last_crawl_at' => 'datetime',
            'indexable' => 'boolean',
            'priority' => 'integer',
            'search_clicks' => 'integer',
            'search_impressions' => 'integer',
            'revenue_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GrowthEvent::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(GrowthAttribution::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function revenueDollars(): float
    {
        return round($this->revenue_cents / 100, 2);
    }
}
