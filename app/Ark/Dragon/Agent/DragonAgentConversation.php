<?php

namespace App\Ark\Dragon\Agent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DragonAgentConversation extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'station_device_token_id',
        'provider',
        'model',
        'pending_memory',
    ];

    protected function casts(): array
    {
        return [
            'pending_memory' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DragonAgentMessage::class)->orderBy('id');
    }

    public function traces(): HasMany
    {
        return $this->hasMany(DragonAgentTrace::class)->orderBy('id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(DragonAgentUsage::class)->orderBy('id');
    }
}
