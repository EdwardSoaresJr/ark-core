<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePublication extends Model
{
    protected $table = 'website_publications';

    protected $fillable = [
        'website_site_id',
        'version',
        'document',
        'content_hash',
        'is_current',
        'published_at',
        'published_by_user_id',
        'source_draft_revision',
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'is_current' => 'boolean',
            'published_at' => 'datetime',
            'version' => 'integer',
            'source_draft_revision' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'website_site_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
