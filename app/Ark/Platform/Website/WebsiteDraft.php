<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteDraft extends Model
{
    protected $table = 'website_drafts';

    protected $fillable = [
        'website_site_id',
        'document',
        'revision',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'revision' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'website_site_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsiteDraftRevision::class);
    }
}
