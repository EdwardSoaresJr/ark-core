<?php

namespace App\Ark\Operations\Conversations;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'growth_session_id',
    'contact_surface',
    'contact_address',
    'status',
    'owned_by_user_id',
    'waiting_on',
    'posture_changed_at',
    'resolved_at',
    'reopen_count',
])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'contact_surface' => ConversationContactSurface::class,
            'status' => ConversationStatus::class,
            'waiting_on' => ConversationWaitingOn::class,
            'posture_changed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'reopen_count' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpenPosture(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open->value);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by_user_id');
    }

    public function growthSession(): BelongsTo
    {
        return $this->belongsTo(\App\Ark\Growth\Models\GrowthSession::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ConversationLink::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }
}
