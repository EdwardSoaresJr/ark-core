<?php

namespace App\Ark\Operations\Recommendations;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationResolution extends Model
{
    protected $table = 'recommendation_resolutions';

    protected $fillable = [
        'recommendation_kind',
        'aggregate_type',
        'aggregate_id',
        'completed_by_user_id',
        'completion_event',
        'outcome_label',
        'title_snapshot',
        'pressure_since',
        'completed_at',
        'elapsed_minutes',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'completed_by_user_id' => 'integer',
            'pressure_since' => 'datetime',
            'completed_at' => 'datetime',
            'elapsed_minutes' => 'integer',
        ];
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
