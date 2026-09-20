<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDraftRevision extends Model
{
    public $timestamps = false;

    protected $table = 'website_draft_revisions';

    protected $fillable = [
        'website_draft_id',
        'revision',
        'document',
        'action',
        'core_hash',
        'actor_user_id',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'meta' => 'array',
            'revision' => 'integer',
            'action' => WebsiteDraftRevisionAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(WebsiteDraft::class, 'website_draft_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
