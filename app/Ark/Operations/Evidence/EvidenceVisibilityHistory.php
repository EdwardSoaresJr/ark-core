<?php

namespace App\Ark\Operations\Evidence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceVisibilityHistory extends Model
{
    public $timestamps = false;

    protected $table = 'evidence_visibility_history';

    protected $fillable = [
        'evidence_id',
        'old_visibility',
        'new_visibility',
        'changed_by_user_id',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_visibility' => EvidenceVisibility::class,
            'new_visibility' => EvidenceVisibility::class,
            'changed_at' => 'datetime',
        ];
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
