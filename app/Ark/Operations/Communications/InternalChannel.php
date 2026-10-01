<?php

namespace App\Ark\Operations\Communications;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'is_private',
])]
class InternalChannel extends Model
{
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(InternalChannelMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'internal_channel_members')
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InternalMessage::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
