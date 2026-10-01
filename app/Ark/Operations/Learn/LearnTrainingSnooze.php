<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnTrainingSnooze extends Model
{
    protected $table = 'learn_training_snoozes';

    protected $fillable = [
        'user_id',
        'snoozed_at',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'snoozed_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->snoozed_until !== null
            && $this->snoozed_until->isFuture();
    }
}
