<?php

namespace App\Ark\Operations\Evidence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EvidenceAttachment extends Model
{
    protected $table = 'evidence_attachments';

    protected $fillable = [
        'evidence_id',
        'attachable_type',
        'attachable_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
